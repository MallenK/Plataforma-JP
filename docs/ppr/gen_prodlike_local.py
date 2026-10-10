"""Genera jp_prodlike.sql: esquema de prod (desde paso1) + datos anonimizados
(paso2) con nombres falsos locales. Uso: python gen_prodlike.py <dir_export> <nombres_locales.tsv> <hash> <out.sql>"""
import json, sys, collections as C, re

EXP, NAMES, PWHASH, OUT = sys.argv[1:5]
DB = "jp_prodlike"

def unesc(s):
    return s.replace("\\\\", "\x00").replace("\\n", "\n").replace("\\t", "\t").replace("\\0", "").replace("\x00", "\\")

def q(v):
    if v is None:
        return "NULL"
    if isinstance(v, bool):
        return "1" if v else "0"
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"

# ── 1. Esquema ───────────────────────────────────────────────────────────────
cols, tables = C.defaultdict(list), {}
with open(EXP + "/paso1_estructura.tsv", encoding="utf-8") as f:
    next(f)
    for line in f:
        p = [unesc(x) for x in line.rstrip("\n").split("\t")]
        p += [""] * (6 - len(p))
        tipo, tabla, nombre, detalle, e1, e2 = p
        if tipo == "columna":
            cols[tabla].append((nombre, detalle, e1, e2))
        elif tipo == "tabla":
            tables[tabla] = {"engine": nombre, "collation": e1, "ai": e2}

COLL = {"utf8mb4_uca1400_ai_ci": "utf8mb4_0900_ai_ci"}
TEXTY = re.compile(r"^(tiny|medium|long)?(text|blob)|^json", re.I)
out = [f"DROP DATABASE IF EXISTS {DB};",
       f"CREATE DATABASE {DB} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
       f"USE {DB};", "SET FOREIGN_KEY_CHECKS=0;", "SET NAMES utf8mb4;", "SET sql_mode='NO_ENGINE_SUBSTITUTION';"]

for t in sorted(cols):
    defs, pk, idx = [], [], []
    for name, ctype, nullable, extra2 in cols[t]:
        left, _, dflt = extra2.partition("default=")
        has_default = "default=" in extra2
        toks = left.split()
        key = toks[0] if toks and toks[0] in ("PRI", "UNI", "MUL") else ""
        extra = " ".join(toks[1:] if key else toks).lower()
        d = f"`{name}` {ctype}"
        d += " NULL" if nullable == "YES" else " NOT NULL"
        if "auto_increment" in extra:
            d += " AUTO_INCREMENT"
        elif has_default and not TEXTY.match(ctype):
            dv = dflt.strip()
            if dv.upper() == "NULL":
                d += " DEFAULT NULL" if nullable == "YES" else ""
            elif dv.lower().startswith("current_timestamp"):
                d += " DEFAULT CURRENT_TIMESTAMP"
            else:
                d += f" DEFAULT {dv}"
        if "on update current_timestamp" in extra:
            d += " ON UPDATE CURRENT_TIMESTAMP"
        defs.append(d)
        if key == "PRI":
            pk.append(name)
        elif key == "UNI":
            idx.append(f"UNIQUE KEY `uq_{name}` (`{name}`)")
        elif key == "MUL":
            idx.append(f"KEY `idx_{name}` (`{name}`)")
    if pk:
        defs.append("PRIMARY KEY (" + ",".join(f"`{c}`" for c in pk) + ")")
    if t == "class_session_players":
        idx.append("UNIQUE KEY `uq_csp` (`session_id`,`user_id`)")
    defs += idx
    ti = tables.get(t, {})
    coll = COLL.get(ti.get("collation"), ti.get("collation") or "utf8mb4_unicode_ci")
    ai = ti.get("ai")
    opts = f" ENGINE={ti.get('engine') or 'InnoDB'} DEFAULT CHARSET=utf8mb4 COLLATE={coll}"
    if ai and ai.isdigit():
        opts += f" AUTO_INCREMENT={ai}"
    out.append(f"CREATE TABLE `{t}` (\n  " + ",\n  ".join(defs) + f"\n){opts};")

# ── 2. Datos anonimizados ────────────────────────────────────────────────────
rows = C.defaultdict(list)
with open(EXP + "/paso2_datos.tsv", encoding="utf-8") as f:
    next(f)
    for line in f:
        t, _, j = line.rstrip("\n").partition("\t")
        rows[t].append(json.loads(unesc(j)))

local = C.defaultdict(list)
for line in open(NAMES, encoding="utf-8"):
    role, _, name = line.rstrip("\n").partition("\t")
    if name and not name.upper().startswith("TEST") and name not in local[role]:
        local[role].append(name)
USED = set()

FIRST = ['Pau','Marc','Nil','Biel','Eric','Bruno','Aleix','Arnau','Gerard','Oriol','Martina','Laia','Emma','Julia',
         'Aina','Carla','Clàudia','Judith','Paula','Alba','Hugo','Dani','Adrià','Iker','Rayan','Youssef','Mohamed',
         'Diego','Mateo','Leo','Izan','Jan','Enzo','Roc','Guim','Ivet','Naia','Ona','Vera']
LAST = ['García','Martínez','López','Fernández','Pérez','Gómez','Sánchez','Romero','Navarro','Torres','Vázquez',
        'Serra','Prat','Vila','Balcells','Bonet','Costa','Marín','Ortega','Ramos']

def name_pool(role, n):
    pool = [x for x in local.get(role, []) if x not in USED]
    if role in ("superadmin", "admin", "staff") and len(pool) < n:
        pool += [x for r in ("admin", "staff", "coach") for x in local.get(r, []) if x not in pool and x not in USED]
    i = 0
    while len(pool) < n:
        cand = f"{FIRST[(i * 7) % len(FIRST)]} {LAST[i % len(LAST)]} {LAST[(i * 3 + 1) % len(LAST)]}"
        if cand not in pool and cand not in USED:
            pool.append(cand)
        i += 1
    USED.update(pool[:n])
    return pool[:n]

ROLE_ORDER = {"superadmin": 0, "admin": 1, "staff": 2, "coach": 3}
equipo = sorted(rows["equipo"], key=lambda r: (ROLE_ORDER.get(r["rol"], 9), r["alta_mes"] or "", r["equipo"]))
alumnos = sorted(rows["alumnos"], key=lambda r: (r["alta_mes"] or "", r["alumno"]))

uid, users, by_role = {}, [], C.defaultdict(list)
for role in ROLE_ORDER:
    grp = [r for r in equipo if r["rol"] == role]
    for r, nm in zip(grp, name_pool(role, len(grp))):
        uid[r["equipo"]] = len(users) + 1
        users.append((uid[r["equipo"]], nm, role, r["estado"], r["alta_mes"]))
        by_role[role].append(uid[r["equipo"]])
base = 1000
for i, (r, nm) in enumerate(zip(alumnos, name_pool("player", len(alumnos)))):
    uid[r["alumno"]] = base + i
    users.append((base + i, nm, "player", r["estado"], r["alta_mes"]))

def actor(role, k=0):
    ids = by_role.get(role) or by_role.get("admin") or [1]
    return ids[k % len(ids)] if role else None

EMAIL = {"player": "alumno", "coach": "entrenador"}
ins = lambda t, cs, vals: out.append(f"INSERT INTO `{t}` ({','.join('`'+c+'`' for c in cs)}) VALUES\n" +
                                     ",\n".join("(" + ",".join(q(v) for v in row) + ")" for row in vals) + ";") if vals else None

ins("users", ["id","name","email","password","password_changed_at","must_change_password","role","status","created_at","updated_at","welcomed_at"],
    [(i, nm, f"{EMAIL.get(role, role)}.{i}@prodlike.local", PWHASH, "2026-09-01 00:00:00", 0, role, st,
      f"{(am or '2026-09')}-15 10:00:00", f"{(am or '2026-09')}-15 10:00:00", f"{(am or '2026-09')}-15 10:05:00")
     for i, nm, role, st, am in users])

TEAMS = ['CF Sant Vicenç A','CF Sant Vicenç B','UE Santboiana','CE Sabadell','CF Vallirana','FC Martorell','UD Sant Boi','CF Pallejà']
prof = []
for r in alumnos:
    i = uid[r["alumno"]]
    bd = None
    if r["edad_franja"]:
        a = int(str(r["edad_franja"]).split("-")[0])
        bd = f"{max(1, 2026 - a - 1):04d}-{1 + i % 12:02d}-{1 + i % 28:02d}"
    prof.append((i, i, bd, r["posicion"], r["nivel"], r["categoria"], TEAMS[i % 8] if r["categoria"] else None,
                 f"{(r['alta_mes'] or '2026-09')}-15 10:00:00"))
ins("player_profiles", ["id","player_id","birth_date","position","level","category","team","created_at"], prof)

ins("bono_types", ["id","name","sessions","price","validity_days","active","created_at","updated_at"],
    [(r["id"], r["nombre"], r["sesiones"], r["precio"], r["validez_dias"], r["activo"], r["creado"], r["actualizado"]) for r in rows["bono_types"]])

ins("player_bonos", ["id","player_id","bono_type_id","sessions_total","sessions_remaining","start_date","expires_at","notes","created_by","created_at","updated_at"],
    [(r["id"], uid.get(r["alumno"]), r["tipo_id"], r["sesiones_total"], r["sesiones_quedan"], r["inicio"], r["caduca"],
      "Nota de prueba (pendiente de pago)" if "pendiente" in (r["notas_claves"] or "") else ("Nota de prueba." if r["tiene_notas"] else None),
      actor(r["creado_por_rol"], r["id"]), r["creado"], r["actualizado"]) for r in rows["player_bonos"]])

sess = {r["id"]: r for r in rows["class_sessions"]}
coach_of = {}
for r in rows["class_session_coaches"]:
    coach_of.setdefault(r["sesion_id"], uid.get(r["equipo"]))

ins("bono_movements", ["id","player_id","bono_id","related_bono_id","session_id","type","delta","note","actor_id","created_at"],
    [(r["id"], uid.get(r["alumno"]), r["bono_id"], r["bono_relacionado_id"], r["sesion_id"], r["tipo"], r["delta"],
      f"Nota de prueba ({r['tipo']})" if r["tiene_nota"] else None,
      (coach_of.get(r["sesion_id"]) if r["actor_rol"] == "coach" and coach_of.get(r["sesion_id"]) else actor(r["actor_rol"], r["id"])),
      r["creado"]) for r in rows["bono_movements"]])

ins("locations", ["id","name","type","active"], [(r["id"], r["nombre"], r["tipo"], r["activa"]) for r in rows["locations"]])

# Título de sesión: "Individual · Alumno" / "Duo · A / B" (como haría la academia)
players_of = C.defaultdict(list)
for r in sorted(rows["class_session_players"], key=lambda r: r["id"]):
    players_of[r["sesion_id"]].append(uid.get(r["alumno"]))
uname = {u[0]: u[1] for u in users}
def title(sid, fmt):
    ps = [uname[p] for p in players_of.get(sid, []) if p in uname]
    return ("Duo" if fmt == "pareja" else "Individual") + (" · " + " / ".join(ps) if ps else "")

first_sess = {}
for s in sorted(rows["class_sessions"], key=lambda s: s["id"]):
    if s["clase_id"] and s["clase_id"] not in first_sess:
        first_sess[s["clase_id"]] = s
ins("classes", ["id","title","type","recurrence_days","recurrence_start","recurrence_end","recurrence_time_start","recurrence_time_end",
                "class_format","default_location_id","created_by","renewed_from_class_id","renewed_to_class_id","created_at","updated_at"],
    [(c["id"], title(first_sess[c["id"]]["id"], c["formato"]) if c["id"] in first_sess else ("Duo" if c["formato"] == "pareja" else "Individual"),
      c["tipo"], c["dias"], c["desde"], c["hasta"], c["hora_inicio"], c["hora_fin"], c["formato"], c["sede_id"],
      actor("admin", c["id"]), c["renovada_de"], c["renovada_a"], c["creado"], c["creado"]) for c in rows["classes"]])

ins("class_sessions", ["id","class_id","title","session_date","start_time","end_time","location_id","status","created_by",
                       "created_at","updated_at","lista_pasada_at","class_format","session_type"],
    [(s["id"], s["clase_id"], title(s["id"], s["formato"]), s["fecha"], s["hora_inicio"], s["hora_fin"], s["sede_id"], s["estado"],
      actor(s["creado_por_rol"] or "admin", s["id"]), s["creado"], s["actualizado"], s["lista_pasada"], s["formato"], s["tipo_sesion"])
     for s in rows["class_sessions"]])

csc_cols = [c[0] for c in cols["class_session_coaches"]]
ins("class_session_coaches", [c for c in ("session_id", "user_id") if c in csc_cols],
    [(r["sesion_id"], uid.get(r["equipo"])) for r in rows["class_session_coaches"]])

ins("class_session_players", ["id","session_id","user_id","coach_id","attendance","absence_reason","absence_notes","student_note",
                              "student_noted_at","responded_at","bono_deducted_at","bono_deducted_from_id","bono_coverage",
                              "bono_resolution","bono_resolved_at","bono_resolved_by","created_at","updated_at"],
    [(r["id"], r["sesion_id"], uid.get(r["alumno"]), uid.get(r["entrenador"]) if r["entrenador"] else None, r["asistencia"],
      "Motivo de prueba" if r["tiene_motivo"] else None, "Nota de ausencia de prueba" if r["tiene_nota_ausencia"] else None,
      "Aviso del alumno (prueba)" if r["tiene_nota_alumno"] else None, r["aviso_alumno"], r["respondido"],
      r["bono_descontado"], r["bono_descontado_de"], r["cobertura"], r["resolucion"], r["resuelto"],
      actor(r["resuelto_por_rol"]) if r["resuelto_por_rol"] else None, r["creado"], r["actualizado"])
     for r in rows["class_session_players"]])

# Ajustes: los locales + las claves de prod que sí conocemos
out.append(f"INSERT INTO {DB}.academy_settings (setting_key, setting_value, setting_type, updated_at) "
           "SELECT setting_key, setting_value, setting_type, updated_at FROM jp_preparation.academy_settings;")
for r in rows["academy_settings"]:
    out.append(f"INSERT INTO academy_settings (setting_key, setting_value, setting_type, updated_at) VALUES ({q(r['clave'])}, {q(r['valor'])}, 'string', NOW()) "
               f"ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);")

out.append("SET FOREIGN_KEY_CHECKS=1;")
open(OUT, "w", encoding="utf-8").write("\n".join(out) + "\n")
print("tablas:", len(cols), "| usuarios:", len(users), "| equipo:", {k: len(v) for k, v in by_role.items()},
      "| alumnos sin nombre local:", max(0, len(alumnos) - len(local['player'])))
