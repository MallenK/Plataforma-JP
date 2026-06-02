<?php
/**
 * Academia JP Fútbol — Seed Data Generator
 *
 * Genera seed_data.sql amb dades realistes per a tota la plataforma.
 * Ús:    docker exec jp_app php /var/www/html/app/Database/Seeds/generate_seed.php
 * Resultat: /var/www/html/app/Database/Seeds/seed_data.sql
 */

date_default_timezone_set('Europe/Madrid');

const HASH         = '$2y$10$CpT8o1IGNi26KZ2wytMIXOpBIDPXpy9044EM/0wD/vClirN7uLNLi';
const TODAY        = '2026-06-02';
const RANGE_START  = '2026-03-02';
const RANGE_END    = '2026-07-02';
const OUTPUT_FILE  = __DIR__ . '/seed_data.sql';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
$out = '';
function out(string $s = ''): void { global $out; $out .= $s . "\n"; }
function q(?string $v): string {
    if ($v === null) return 'NULL';
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
}
function qi(?int $v): string { return $v === null ? 'NULL' : (string)$v; }
function qf(?float $v): string { return $v === null ? 'NULL' : number_format($v, 2, '.', ''); }
function att(int $playerId, string $date, bool $past): string {
    $h = abs(crc32($playerId . $date)) % 100;
    if ($past) { return $h < 5 ? 'declined' : ($h < 22 ? 'absent' : 'present'); }
    return $h < 10 ? 'declined' : ($h < 42 ? 'confirmed' : 'pending');
}
function pickFocus(int $seed, array $arr): ?string {
    return $arr[abs($seed) % count($arr)];
}

// ---------------------------------------------------------------------------
// STATIC DATA
// ---------------------------------------------------------------------------

$locations = [
    [1002,'Camp Principal','Camp de futbol 11 principal per a competicions de lliga','Carrer dels Esports, 1, Barcelona','pitch',300,'93 555 11 00'],
    [1003,'Camp B','Camp de futbol 7 per a categories base i entrenaments','Carrer dels Esports, 3, Barcelona','pitch',150,null],
    [1004,'Sala de Vídeo',"Sala d'anàlisi tàctica, vídeo i xerrades",'Carrer dels Esports, 1 (interior)','room',30,null],
    [1005,'Gimnàs','Sala de preparació física i estirades','Carrer dels Esports, 1 (planta baixa)','gym',20,null],
    [1006,'Despatx Coordinació','Oficina de coordinació, reunions i administració','Carrer dels Esports, 1 (1r pis)','office',8,null],
];

// [id, name, email, staff_title]
$coaches = [
    [1000,'Marc Puig Batlle',         'marc.puig@academiajp.cat',         'Entrenador Primer Equip'],
    [1001,'Jordi Fernández Costa',     'jordi.fernandez@academiajp.cat',   'Preparador Físic'],
    [1002,'Pau Soler Riera',           'pau.soler@academiajp.cat',         'Entrenador de Porters'],
    [1003,'Arnau Vila Martí',          'arnau.vila@academiajp.cat',        'Entrenador Base (Infantil/Cadet)'],
    [1004,'Xavier Puigdomènech Valls', 'xavier.puigdomenech@academiajp.cat','Entrenador Base (Prebenjamí/Benjamí)'],
    [1005,'Laia Ferrer Camps',         'laia.ferrer@academiajp.cat',       'Entrenadora Tècnica'],
    [1006,'Roger Mas Vidal',           'roger.mas@academiajp.cat',         'Coordinador de Categories'],
];

$staff = [
    [1007,'Marta Bosch Queralt', 'marta.bosch@academiajp.cat',  'Fisioterapeuta'],
    [1008,'Enric Llopis Torres', 'enric.llopis@academiajp.cat', "Delegat d'Equip"],
    [1009,'Toni Gallart Pérez',  'toni.gallart@academiajp.cat', 'Utiller'],
    [1010,'Núria Sala Prat',     'nuria.sala@academiajp.cat',   'Coordinadora Administrativa'],
];

// [id, name, email, birth_date, position, level, category, team, league, height, weight, medical_notes]
$players = [
    // PREBENJAMÍ (1011-1018)
    [1011,'Biel Puig Torres',     'biel.puig@academiajp.cat',     '2018-03-15','portero',         'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',122,22,null],
    [1012,'Pau Ferrer Mas',       'pau.ferrer@academiajp.cat',    '2018-07-22','extrem dret',     'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',120,21,null],
    [1013,'Laia Vila Roca',       'laia.vila@academiajp.cat',     '2019-01-10','migcampista',     'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',118,20,null],
    [1014,'Marc Soler Gómez',     'marc.soler@academiajp.cat',    '2018-11-05','defensa central', 'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',121,22,null],
    [1015,'Jana Costa Bosch',     'jana.costa@academiajp.cat',    '2019-04-18','davanter centre', 'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',116,19,null],
    [1016,'Arnau Pérez Vidal',    'arnau.perez@academiajp.cat',   '2018-08-30','lateral dret',    'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',120,21,null],
    [1017,'Noa Martí Sala',       'noa.marti@academiajp.cat',     '2019-02-14','extrem esquerre', 'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',115,18,null],
    [1018,'Èric Camps Llopis',    'eric.camps@academiajp.cat',    '2018-05-28','pivot',           'beginner','prebenjamin','Prebenjamí A','Lliga Catalana Prebenjamí',119,20,null],
    // BENJAMÍ (1019-1028)
    [1019,'Jordi Batlle Mas',     'jordi.batlle@academiajp.cat',  '2016-09-12','portero',         'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',138,32,null],
    [1020,'Anna Riera Costa',     'anna.riera@academiajp.cat',    '2017-03-25','defensa central', 'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',135,30,null],
    [1021,'Pau Domènech Gil',     'pau.domenech@academiajp.cat',  '2016-12-08','lateral dret',    'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',140,33,null],
    [1022,'Carla Puig Martí',     'carla.puig@academiajp.cat',    '2017-06-14','migcampista',     'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',133,29,null],
    [1023,'Nil Ferrer Bosch',     'nil.ferrer@academiajp.cat',    '2016-10-30','pivot',           'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',142,35,null],
    [1024,'Marta Sala Torres',    'marta.sala@academiajp.cat',    '2017-01-19','extrem dret',     'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',131,28,null],
    [1025,'Àlex Vila Camps',      'alex.vila@academiajp.cat',     '2016-11-22','davanter centre', 'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',143,36,null],
    [1026,'Júlia Soler Pérez',    'julia.soler@academiajp.cat',   '2017-05-08','extrem esquerre', 'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',132,29,null],
    [1027,'Hugo Gómez Riera',     'hugo.gomez@academiajp.cat',    '2016-08-16','lateral esquerre','beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',141,34,null],
    [1028,'Ona Llopis Vidal',     'ona.llopis@academiajp.cat',    '2017-07-03','mediapunta',      'beginner','benjamin','Benjamí A','Lliga Catalana Benjamí',130,28,null],
    // ALEVÍ (1029-1038)
    [1029,'Sergi Mas Batlle',     'sergi.mas@academiajp.cat',     '2014-08-22','portero',         'intermediate','alevin','Aleví A','Primera Divisió Aleví',155,45,null],
    [1030,'Laia Pérez Sala',      'laia.perez@academiajp.cat',    '2015-02-14','defensa central', 'intermediate','alevin','Aleví A','Primera Divisió Aleví',150,40,null],
    [1031,'Dani Costa Gil',       'dani.costa@academiajp.cat',    '2014-11-30','lateral dret',    'intermediate','alevin','Aleví A','Primera Divisió Aleví',158,47,null],
    [1032,'Alba Roca Martí',      'alba.roca@academiajp.cat',     '2015-04-06','migcampista',     'intermediate','alevin','Aleví A','Primera Divisió Aleví',148,39,null],
    [1033,'Iker Puig Torres',     'iker.puig@academiajp.cat',     '2014-07-18','pivot',           'intermediate','alevin','Aleví A','Primera Divisió Aleví',160,48,null],
    [1034,'Mireia Ferrer Mas',    'mireia.ferrer@academiajp.cat', '2015-01-25','extrem dret',     'intermediate','alevin','Aleví A','Primera Divisió Aleví',146,38,null],
    [1035,'Joel Vila Riera',      'joel.vila@academiajp.cat',     '2014-10-12','davanter centre', 'intermediate','alevin','Aleví A','Primera Divisió Aleví',162,49,null],
    [1036,'Gemma Bosch Camps',    'gemma.bosch@academiajp.cat',   '2015-03-20','extrem esquerre', 'intermediate','alevin','Aleví A','Primera Divisió Aleví',149,39,null],
    [1037,'Adrià Soler Llopis',   'adria.soler@academiajp.cat',   '2014-09-03','lateral esquerre','intermediate','alevin','Aleví A','Primera Divisió Aleví',157,46,null],
    [1038,'Neus Vidal Gil',       'neus.vidal@academiajp.cat',    '2015-06-28','mediapunta',      'intermediate','alevin','Aleví A','Primera Divisió Aleví',147,38,null],
    // INFANTIL (1039-1050)
    [1039,'Pol Martí Costa',      'pol.marti@academiajp.cat',     '2012-10-15','portero',         'intermediate','infantil','Infantil A','Primera Divisió Infantil',168,57,null],
    [1040,'Júlia Batlle Puig',    'julia.batlle@academiajp.cat',  '2013-03-22','defensa central', 'intermediate','infantil','Infantil A','Primera Divisió Infantil',163,52,null],
    [1041,'Marc Sala Ferrer',     'marc.sala@academiajp.cat',     '2012-07-08','lateral dret',    'intermediate','infantil','Infantil A','Primera Divisió Infantil',172,60,null],
    [1042,'Carla Torres Soler',   'carla.torres@academiajp.cat',  '2013-01-14','migcampista',     'intermediate','infantil','Infantil A','Primera Divisió Infantil',161,51,null],
    [1043,'Arnau Camps Roca',     'arnau.camps@academiajp.cat',   '2012-09-25','pivot',           'intermediate','infantil','Infantil A','Primera Divisió Infantil',170,59,null],
    [1044,'Anna Pérez Mas',       'anna.perez@academiajp.cat',    '2013-05-30','extrem dret',     'intermediate','infantil','Infantil A','Primera Divisió Infantil',159,50,null],
    [1045,'Gerard Riera Domènech','gerard.riera@academiajp.cat',  '2012-11-18','davanter centre', 'intermediate','infantil','Infantil A','Primera Divisió Infantil',173,62,null],
    [1046,'Laia Vidal Bosch',     'laia.vidal@academiajp.cat',    '2013-02-07','extrem esquerre', 'intermediate','infantil','Infantil A','Primera Divisió Infantil',160,51,null],
    [1047,'Àlex Gil Torres',      'alex.gil@academiajp.cat',      '2012-08-12','lateral esquerre','intermediate','infantil','Infantil A','Primera Divisió Infantil',171,60,null],
    [1048,'Marta Llopis Vila',    'marta.llopis@academiajp.cat',  '2013-04-25','mediapunta',      'intermediate','infantil','Infantil A','Primera Divisió Infantil',162,52,null],
    [1049,'Pau Gómez Sala',       'pau.gomez@academiajp.cat',     '2012-12-03','defensa central', 'intermediate','infantil','Infantil A','Primera Divisió Infantil',169,58,null],
    [1050,'Ona Costa Ferrer',     'ona.costa@academiajp.cat',     '2013-07-16','migcampista',     'intermediate','infantil','Infantil A','Primera Divisió Infantil',164,53,null],
    // CADET (1051-1062)
    [1051,'Roger Puig Costa',     'roger.puig@academiajp.cat',    '2010-09-14','portero',         'intermediate','cadete','Cadet A','Primera Divisió Cadet',178,68,null],
    [1052,'Marina Mas Batlle',    'marina.mas@academiajp.cat',    '2011-02-28','defensa central', 'intermediate','cadete','Cadet A','Primera Divisió Cadet',170,60,null],
    [1053,'Àlex Roca Sala',       'alex.roca@academiajp.cat',     '2010-07-05','lateral dret',    'intermediate','cadete','Cadet A','Primera Divisió Cadet',180,71,"Al·lèrgia a l'ibuprofèn. Evitar antiinflamatoris sense valoració mèdica prèvia."],
    [1054,'Neus Ferrer Camps',    'neus.ferrer@academiajp.cat',   '2011-04-18','migcampista',     'intermediate','cadete','Cadet A','Primera Divisió Cadet',166,57,null],
    [1055,'Dani Soler Martí',     'dani.soler@academiajp.cat',    '2010-11-22','pivot',           'intermediate','cadete','Cadet A','Primera Divisió Cadet',179,70,null],
    [1056,'Gemma Vila Pérez',     'gemma.vila@academiajp.cat',    '2011-01-10','extrem dret',     'intermediate','cadete','Cadet A','Primera Divisió Cadet',165,56,null],
    [1057,'Jordi Torres Gil',     'jordi.torres@academiajp.cat',  '2010-08-30','davanter centre', 'intermediate','cadete','Cadet A','Primera Divisió Cadet',181,72,null],
    [1058,'Alba Bosch Riera',     'alba.bosch@academiajp.cat',    '2011-06-05','extrem esquerre', 'intermediate','cadete','Cadet A','Primera Divisió Cadet',168,58,null],
    [1059,'Joel Batlle Vidal',    'joel.batlle@academiajp.cat',   '2010-10-19','lateral esquerre','intermediate','cadete','Cadet A','Primera Divisió Cadet',177,67,"Esguinç de turmell esquerre (oct 2025). Recuperació completa al gener 2026."],
    [1060,'Mireia Costa Sala',    'mireia.costa@academiajp.cat',  '2011-03-14','mediapunta',      'intermediate','cadete','Cadet A','Primera Divisió Cadet',163,54,null],
    [1061,'Iker Llopis Gómez',    'iker.llopis@academiajp.cat',   '2010-12-25','defensa central', 'intermediate','cadete','Cadet A','Primera Divisió Cadet',180,70,null],
    [1062,'Laia Camps Domènech',  'laia.camps@academiajp.cat',    '2011-05-09','pivot',           'intermediate','cadete','Cadet A','Primera Divisió Cadet',167,57,null],
    // JUVENIL (1063-1072)
    [1063,'Pol Puig Martí',       'pol.puig@academiajp.cat',      '2008-11-15','portero',         'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",183,75,null],
    [1064,'Carla Riera Torres',   'carla.riera@academiajp.cat',   '2009-03-22','defensa central', 'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",174,63,null],
    [1065,'Marc Mas Gil',         'marc.mas@academiajp.cat',      '2008-08-08','lateral dret',    'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",183,76,null],
    [1066,'Anna Vila Ferrer',     'anna.vila@academiajp.cat',     '2009-01-17','migcampista',     'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",170,62,null],
    [1067,'Arnau Batlle Soler',   'arnau.batlle@academiajp.cat',  '2008-06-25','pivot',           'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",185,78,"Pronació de peu dret. Ortopèdia personalitzada. Control periòdic cada 3 mesos."],
    [1068,'Laia Prats Vidal',     'laia.prats@academiajp.cat',    '2009-04-12','extrem dret',     'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",169,61,null],
    [1069,'Gerard Costa Bosch',   'gerard.costa@academiajp.cat',  '2008-10-30','davanter centre', 'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",182,76,null],
    [1070,'Mireia Sala Mas',      'mireia.sala@academiajp.cat',   '2009-02-05','extrem esquerre', 'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",171,62,null],
    [1071,'Iker Roca Ferrer',     'iker.roca@academiajp.cat',     '2008-09-18','lateral esquerre','advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",182,75,null],
    [1072,'Gemma Torres Soler',   'gemma.torres@academiajp.cat',  '2009-06-03','mediapunta',      'advanced','juvenil',"Juvenil A","Divisió d'Honor Juvenil",172,63,null],
    // JÚNIOR (1073-1080)
    [1073,'Pol Masferrer Roca',   'pol.masferrer@academiajp.cat', '2006-09-15','portero',         'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",185,80,null],
    [1074,'Carla Domènech Puig',  'carla.domenech@academiajp.cat','2007-02-22','defensa central', 'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",174,64,null],
    [1075,'Marc Batlle Torres',   'marc.batlle@academiajp.cat',   '2006-11-08','lateral dret',    'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",182,78,null],
    [1076,'Anna Camps Costa',     'anna.camps@academiajp.cat',    '2007-04-17','migcampista',     'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",169,61,null],
    [1077,'Àlex Ferrer Sala',     'alex.ferrer@academiajp.cat',   '2006-07-25','pivot',           'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",183,79,null],
    [1078,'Neus Bosch Vidal',     'neus.bosch@academiajp.cat',    '2007-01-12','extrem dret',     'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",168,62,null],
    [1079,'Jordi Gil Mas',        'jordi.gil@academiajp.cat',     '2006-10-30','davanter centre', 'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",181,77,null],
    [1080,'Alba Soler Pérez',     'alba.soler@academiajp.cat',    '2007-06-03','extrem esquerre', 'advanced','junior',"Júnior A / Primer Equip","Tercera Divisió RFEF",172,63,null],
];

// [id, bono_type_id, sessions, price, validity_days, active]
$bonoTypes = [
    [1001,'Sessió Individual',   1, 18.00, 30, 1],
    [1002,'Sessió en Parella',   1, 13.00, 30, 1],
    [1003,'Pack 10 Sessions',   10,150.00, 90, 1],
    [1004,'Mensual Individual', 12, 85.00, 31, 1],
    [1005,'Mensual en Parella', 12, 65.00, 31, 1],
];

// [id, player_id, bono_type_id, sessions_total, sessions_remaining, start_date, expires_at, notes]
$playerBonos = [
    // ACTIVE bonos (expires_at > 2026-06-02)
    [1001,1073,1004,12, 8,'2026-06-01','2026-07-02','Mensual Primer Equip - juny 2026'],
    [1002,1074,1004,12,10,'2026-06-01','2026-07-02',null],
    [1003,1075,1004,12, 7,'2026-06-01','2026-07-02',null],
    [1004,1076,1004,12,11,'2026-06-01','2026-07-02',null],
    [1005,1077,1004,12, 9,'2026-06-01','2026-07-02',null],
    [1006,1078,1004,12,12,'2026-06-01','2026-07-02',null],
    [1007,1079,1004,12, 6,'2026-06-01','2026-07-02',null],
    [1008,1080,1004,12,10,'2026-06-01','2026-07-02',null],
    [1009,1063,1004,12, 9,'2026-05-20','2026-06-20',null],
    [1010,1064,1004,12,11,'2026-05-20','2026-06-20',null],
    [1011,1065,1003,10, 5,'2026-04-15','2026-07-14',null],
    [1012,1066,1004,12, 8,'2026-05-20','2026-06-20',null],
    [1013,1067,1003,10, 3,'2026-04-15','2026-07-14',null],
    [1014,1068,1004,12,10,'2026-05-20','2026-06-20',null],
    [1015,1069,1003,10, 7,'2026-04-15','2026-07-14',null],
    [1016,1070,1004,12,12,'2026-05-20','2026-06-20',null],
    [1017,1071,1003,10, 4,'2026-04-15','2026-07-14',null],
    [1018,1072,1004,12, 9,'2026-05-20','2026-06-20',null],
    [1019,1051,1003,10, 6,'2026-04-01','2026-06-30',null],
    [1020,1052,1005,12, 8,'2026-05-15','2026-06-15',null],
    [1021,1053,1003,10, 4,'2026-04-01','2026-06-30',null],
    [1022,1054,1005,12,10,'2026-05-15','2026-06-15',null],
    [1023,1055,1003,10, 7,'2026-04-01','2026-06-30',null],
    [1024,1056,1005,12, 9,'2026-05-15','2026-06-15',null],
    [1025,1057,1003,10, 2,'2026-04-01','2026-06-30',null],
    [1026,1058,1005,12,11,'2026-05-15','2026-06-15',null],
    [1027,1039,1003,10, 5,'2026-04-10','2026-07-09',null],
    [1028,1042,1002, 1, 1,'2026-06-01','2026-07-01',null],
    [1029,1045,1003,10, 3,'2026-04-10','2026-07-09',null],
    [1030,1047,1001, 1, 1,'2026-06-02','2026-07-02',null],
    // EXPIRED bonos
    [1031,1029,1003,10, 0,'2026-03-01','2026-05-30',null],
    [1032,1031,1003,10, 2,'2026-03-01','2026-05-30',null],
    [1033,1033,1003,10, 5,'2026-03-01','2026-05-30',null],
    [1034,1035,1003,10, 0,'2026-03-01','2026-05-30',null],
    [1035,1037,1004,12, 3,'2026-04-10','2026-05-11',null],
    [1036,1019,1004,12, 0,'2026-03-20','2026-04-20',null],
    [1037,1022,1003,10, 1,'2026-03-01','2026-05-30',null],
    [1038,1025,1004,12, 4,'2026-03-20','2026-04-20',null],
    [1039,1027,1003,10, 2,'2026-03-01','2026-05-30',null],
    [1040,1040,1004,12, 0,'2026-03-20','2026-04-20',null],
    [1041,1043,1003,10, 3,'2026-03-01','2026-05-30',null],
    [1042,1046,1004,12, 5,'2026-03-20','2026-04-20',null],
    [1043,1048,1003,10, 1,'2026-03-01','2026-05-30',null],
    [1044,1059,1004,12, 0,'2026-03-20','2026-04-20',null],
    [1045,1062,1003,10, 2,'2026-03-01','2026-05-30',null],
    // DEPLETED (sessions_remaining=0, expires future or recent)
    [1046,1073,1003,10, 0,'2026-03-01','2026-05-30','Pack anterior completat'],
    [1047,1063,1003,10, 0,'2026-03-01','2026-05-30',null],
    [1048,1065,1003,10, 0,'2026-03-01','2026-05-30',null],
    [1049,1051,1004,12, 0,'2026-03-20','2026-04-20',null],
    [1050,1029,1001, 1, 0,'2026-03-05','2026-04-04',null],
    [1051,1035,1001, 1, 0,'2026-03-10','2026-04-09',null],
    [1052,1039,1003,10, 0,'2026-04-01','2026-06-30','Pack completat abans de caducar'],
    [1053,1045,1004,12, 0,'2026-03-20','2026-04-20',null],
    [1054,1057,1003,10, 0,'2026-03-01','2026-05-30',null],
    [1055,1067,1004,12, 0,'2026-03-20','2026-04-20',null],
];

// Players with active bonos (for bono_deducted_at logic)
$activeBonoPids = array_merge(range(1073,1080), range(1063,1072), [1051,1052,1053,1054,1055,1056,1057,1058,1039,1042,1045,1047]);

// Class definitions: class_id => [title, days(1=Mon), start_t, end_t, loc_id, coaches[], p_start, p_end]
$classDefs = [
    1001 => ["Prebenjamí A",          [1,3], '17:00:00','18:30:00', 1003, [1004],      1011, 1018],
    1002 => ["Benjamí A",             [1,3], '18:30:00','20:00:00', 1003, [1004,1005], 1019, 1028],
    1003 => ["Aleví A",               [2,4], '17:00:00','18:30:00', 1003, [1005,1004], 1029, 1038],
    1004 => ["Infantil A",            [2,4], '18:30:00','20:00:00', 1002, [1003],      1039, 1050],
    1005 => ["Cadet A",               [1,3], '19:30:00','21:00:00', 1002, [1003,1001], 1051, 1062],
    1006 => ["Juvenil A",             [2,4], '19:30:00','21:00:00', 1002, [1000,1001], 1063, 1072],
    1007 => ["Júnior A / Primer Equip",[1,3,5],'20:00:00','21:30:00',1002, [1000,1002], 1073, 1080],
];

$focuses = [
    'Treball tècnic i control de pilota',
    'Pressing alt i recuperació de pilota',
    'Finalització i definició davant porteria',
    'Defensa zonal i transicions defensives',
    'Joc posicional i triangulacions en atac',
    'Velocitat, canvis de ritme i acceleració',
    "Córners, faltes i accions a pilota aturada",
    'Preparació física i circuits de resistència',
    "Circuits tècnics d'habilitat individual",
    'Joc directe i profunditat per les bandes',
    'Construcció des del darrere i pressió alta',
    "Superioritat numèrica i joc en reduït 3v2",
];

$postNotes = [
    'Sessió molt productiva. Bona actitud i concentració del grup durant tot el treball.',
    'Cal millorar el pressing alt. Repetim exercicis de recuperació la propera sessió.',
    "Excel·lent treball en equip. La comunicació dins el camp ha millorat notablement.",
    "Alguns jugadors amb dificultat en els canvis de ritme. Repetirem exercicis d'acceleració.",
    'Molt bon nivell tècnic avui. Satisfets amb la progressió col·lectiva del grup.',
    'Sessió de càrrega baixa per prevenció de lesions. Bona resposta general.',
    "Hem revisat els errors del darrer partit. Bona assimilació de les correccions tàctiques.",
    "Treball de pilota aturada molt productiu. Hem preparat jugades de córner i falta directa.",
    'Sessió física intensa. Molt bona resposta del grup als circuits de resistència.',
    "Bon entrenament. Destaquem la millora en la sortida de pressió des del darrere.",
    null, null, null,
];

// [id, player_id, author_id, type, content, created_at]
$annotations = [
    [1001,1073,1000,'internal',"Pol és el porter titular indiscutible del primer equip. Reflexos excel·lents i bona col·locació. Cal treballar la sortida a les pilotes aèries dins l'àrea.",'2026-04-15 10:30:00'],
    [1002,1063,1000,'public','Pol, has tingut una millora molt notable en el posicionament defensiu aquest mes. Segueix amb la mateixa actitud!','2026-04-20 11:00:00'],
    [1003,1075,1000,'internal','Marc mostra bona velocitat però li costa mantenir la concentració durant els 90 minuts. Treballem la resistència mental.','2026-05-02 09:15:00'],
    [1004,1067,1000,'internal',"Arnau és clarament el jugador amb millor visió de joc del juvenil. Candidat a capità la temporada que ve.",'2026-05-10 16:00:00'],
    [1005,1053,1003,'internal','Àlex ha sofert una lesió lleu al genoll esquerre. Caldrà vigilar la seva càrrega d\'entrenament les properes setmanes.','2026-04-08 18:45:00'],
    [1006,1059,1003,'internal',"Joel s'ha recuperat bé de l'esguinç de turmell. Reincorporat a l'entrenament sense restriccions.",'2026-05-18 19:30:00'],
    [1007,1051,1003,'public','Roger, has fet una primera part de temporada excel·lent. El teu compromís i esforç es nota dia a dia.','2026-04-25 10:00:00'],
    [1008,1039,1003,'public','Pol, bona temporada fins ara. Segueix treballant el joc de peus, és el teu punt de millora principal.','2026-04-12 17:00:00'],
    [1009,1045,1003,'internal','Gerard té molts recursos ofensius però li cal millorar el pressing defensiu. Exercicis específics la setmana que ve.','2026-05-05 20:00:00'],
    [1010,1039,1003,'internal',"Gran millora en la sortida de porteria amb el peu. Continuem treballant-ho amb en Pau (entrenador de porters).",'2026-05-20 18:30:00'],
    [1011,1029,1005,'internal',"Sergi és el millor porter de la categoria. Reflexos excel·lents per la seva edat. Potencial per a categories superiors.",'2026-04-18 18:45:00'],
    [1012,1035,1005,'public','Joel, la teva millora en el control de pilota ha estat notable. Molt content amb el teu progrés.','2026-05-08 19:00:00'],
    [1013,1033,1005,'internal',"Iker és molt intens però necessita aprendre a gestionar l'energia. Tendeix a saturar-se als últims 20 minuts.",'2026-04-28 18:30:00'],
    [1014,1022,1004,'public','Carla, ets la jugadora amb millor lectura del joc de tot el Benjamí. Segueix liderant!','2026-05-15 20:30:00'],
    [1015,1025,1004,'internal',"Àlex té molta projecció ofensiva però li falta treballar el joc d'esquena. Exercicis específics d'atacant.",'2026-04-22 20:00:00'],
    [1016,1019,1004,'public','Jordi, has millorat molt la teva sortida de pilota amb el peu esquerre. Molt bé!','2026-05-20 20:15:00'],
    [1017,1011,1004,'public',"Biel, primera temporada a la categoria i ja destacas pel teu posicionament. Molt ben fet!",'2026-04-10 18:30:00'],
    [1018,1014,1004,'internal',"Marc és el defensa més fiable del Prebenjamí. Molt bon comunicador a l'àrea.",'2026-05-05 18:45:00'],
    [1019,1065,1000,'public','Marc, la teva velocitat amb pilota és un arma diferencial. Treballem la finalització per completar el teu joc.','2026-05-12 21:30:00'],
    [1020,1069,1000,'internal',"Gerard és el davanter amb millor ratio de gol de tota la pedrera. Seguiment especial per la temporada que ve.",'2026-05-25 21:00:00'],
    [1021,1064,1000,'public',"Carla, la teva regularitat defensiva és un exemple per a tot l'equip. Ets molt important en l'estructura.",'2026-04-30 21:15:00'],
    [1022,1077,1002,'internal',"Àlex és el pivot amb més potencial físic que he entrenat. Treball de posicionament els divendres.",'2026-05-08 21:45:00'],
    [1023,1073,1002,'public','Pol, has donat un salt de qualitat enorme des del principi de temporada. La teva implicació és total.','2026-05-22 21:30:00'],
    [1024,1041,1003,'internal',"Marc és el lateral dret més actiu de l'infantil. Cal polir el seu cross per tenir un jugador complet.",'2026-04-14 20:15:00'],
    [1025,1047,1003,'public','Àlex, la teva millora en la fase defensiva ha estat molt notable. Segueix treballant el posicionament!','2026-05-18 20:00:00'],
    [1026,1043,1003,'internal',"Arnau és el millor pivot de la seva edat a la categoria. Lectura de joc molt avançada per la seva edat.",'2026-05-28 20:30:00'],
    [1027,1071,1001,'internal',"Iker necessita millorar la seva resistència aeròbica. Programa de treball físic complementari preparat.",'2026-05-10 21:00:00'],
    [1028,1067,1001,'internal',"Arnau ha mostrat una progressió física extraordinària. És dels millors atletes de la pedrera.",'2026-05-20 21:30:00'],
    [1029,1055,1001,'public','Dani, el teu físic és una qualitat diferencial. Segueix treballant la resistència i la velocitat de reacció.','2026-04-28 21:00:00'],
    [1030,1057,1003,'internal',"Jordi és el davanter centre del futur de l'acadèmia. Cal protegir el seu desenvolupament i no precipitar el seu ascens.",'2026-05-15 21:15:00'],
    [1031,1080,1000,'public',"Alba, la teva versatilitat és la teva millor arma. Pots jugar a múltiples posicions i ho fas molt bé.",'2026-05-28 21:45:00'],
    [1032,1076,1000,'internal',"Anna és la jugadora amb millor visió de joc del primer equip. Cal donar-li més protagonisme als entrenaments.",'2026-05-30 22:00:00'],
    [1033,1079,1000,'internal',"Jordi té un problema de motivació des del febrer. Cal fer un seguiment proper i parlar-hi sobre els seus objectius.",'2026-04-05 22:00:00'],
    [1034,1079,1000,'public','Jordi, hem notat una millora en la teva actitud les últimes setmanes. Segueix amb aquesta energia!','2026-05-28 21:50:00'],
    [1035,1074,1000,'internal',"Carla és la jugadora defensiva amb millor posicionament de tot el primer equip. Rendiment constant i fiable.",'2026-05-10 21:00:00'],
];

// [id, player_id, coach_id, date, metrics_json, evaluation, notes]
$metrics = [
    [1001,1073,1000,'2026-05-15','{"velocitat":9,"resistencia":8,"tecnica":9,"tactica":8,"actitud":10}',"Pol mostra uns valors excel·lents en tots els aspectes. Porter amb un potencial extraordinari. La seva comunicació amb la defensa ha millorat molt.",'Avaluació mensual maig 2026'],
    [1002,1063,1000,'2026-05-15','{"velocitat":8,"resistencia":8,"tecnica":7,"tactica":9,"actitud":9}',"Pol té una lectura tàctica molt madura per la seva edat. Cal seguir polint la tècnica individual.",'Avaluació mensual maig 2026'],
    [1003,1065,1000,'2026-05-15','{"velocitat":10,"resistencia":7,"tecnica":8,"tactica":7,"actitud":8}',"Marc és el jugador més ràpid de la categoria. Necessita treballar la resistència i la presa de decisions.",'Avaluació mensual maig 2026'],
    [1004,1067,1001,'2026-05-16','{"velocitat":8,"resistencia":10,"tecnica":7,"tactica":9,"actitud":9}',"Arnau té una capacitat física excepcional. El seu treball al gimnàs es nota clarament en el rendiment al camp.",'Avaluació física mensual'],
    [1005,1069,1000,'2026-05-15','{"velocitat":8,"resistencia":7,"tecnica":9,"tactica":8,"actitud":9}',"Gerard és el davanter més productiu del juvenil. Ratio de finalització excel·lent.",'Avaluació mensual maig 2026'],
    [1006,1075,1000,'2026-05-16','{"velocitat":9,"resistencia":7,"tecnica":8,"tactica":7,"actitud":7}',"Marc té qualitats físiques superiors però cal millorar la seva actitud en els entrenaments.",'Avaluació mensual maig 2026'],
    [1007,1077,1002,'2026-05-16','{"velocitat":7,"resistencia":9,"tecnica":8,"tactica":8,"actitud":10}',"Àlex és el porter amb millor actitud de tota la pedrera. El seu compromís és un exemple per als seus companys.",'Avaluació mensual pivot/porter'],
    [1008,1051,1003,'2026-05-14','{"velocitat":7,"resistencia":8,"tecnica":8,"tactica":9,"actitud":9}',"Roger és el porter cadet amb millor posicionament. La seva comunicació amb la defensa és excel·lent.",'Avaluació Cadet A - maig'],
    [1009,1057,1003,'2026-05-14','{"velocitat":9,"resistencia":8,"tecnica":8,"tactica":8,"actitud":8}',"Jordi mostra un gran potencial ofensiu. Cal treballar la seva participació en la construcció del joc.",'Avaluació Cadet A - maig'],
    [1010,1055,1001,'2026-05-14','{"velocitat":8,"resistencia":9,"tecnica":7,"tactica":7,"actitud":8}',"Dani té un físic excel·lent per la seva edat. Necessita millorar la tècnica individual.",'Avaluació física Cadet A'],
    [1011,1039,1003,'2026-05-13','{"velocitat":7,"resistencia":7,"tecnica":8,"tactica":8,"actitud":9}',"Pol és el porter infantil amb millor progrés de la temporada. La sortida de pilota ha millorat molt.",'Avaluació Infantil A - maig'],
    [1012,1045,1003,'2026-05-13','{"velocitat":9,"resistencia":7,"tecnica":8,"tactica":7,"actitud":8}',"Gerard té molta qualitat ofensiva però ha de millorar el seu treball defensiu.",'Avaluació Infantil A - maig'],
    [1013,1043,1003,'2026-05-13','{"velocitat":7,"resistencia":8,"tecnica":8,"tactica":9,"actitud":10}',"Arnau és el jugador més intel·ligent tàcticament de tot l'infantil. Actitud de líder.",'Avaluació Infantil A - maig'],
    [1014,1029,1005,'2026-05-12','{"velocitat":8,"resistencia":7,"tecnica":8,"tactica":7,"actitud":9}',"Sergi té reflexos excel·lents per la seva edat. Cal treballar la sortida a pilotes aèries.",'Avaluació Aleví A - maig'],
    [1015,1035,1005,'2026-05-12','{"velocitat":9,"resistencia":8,"tecnica":7,"tactica":7,"actitud":8}',"Joel és el davanter amb més gol de l'aleví. Necessita millorar la participació en el joc de construcció.",'Avaluació Aleví A - maig'],
    [1016,1033,1004,'2026-05-12','{"velocitat":8,"resistencia":7,"tecnica":7,"tactica":8,"actitud":7}',"Iker té molt potencial però necessita aprendre a gestionar la intensitat durant tota la sessió.",'Avaluació Aleví A - maig'],
    [1017,1073,1000,'2026-04-15','{"velocitat":8,"resistencia":8,"tecnica":8,"tactica":7,"actitud":9}',"Avaluació trimestral. Pol ha crescut molt com a porter. La millora més notable és en la comunicació i lideratge.",'Avaluació trimestral abril 2026'],
    [1018,1067,1001,'2026-04-15','{"velocitat":8,"resistencia":9,"tecnica":7,"tactica":8,"actitud":9}',"Arnau continua amb la seva progressió física. Un dels millors perfils físics de la categoria en anys.",'Avaluació trimestral abril 2026'],
    [1019,1063,1000,'2026-04-15','{"velocitat":7,"resistencia":7,"tecnica":7,"tactica":8,"actitud":9}',"Pol mostra una progressió constant. Jugador de procés, molt fiable i consistent.",'Avaluació trimestral abril 2026'],
    [1020,1069,1000,'2026-04-15','{"velocitat":8,"resistencia":7,"tecnica":8,"tactica":7,"actitud":8}',"Gerard ha tingut un bon primer trimestre. La seva capacitat de finalització és l'element diferencial.",'Avaluació trimestral abril 2026'],
];

// Conversations: [id, user1_id, user2_id, created_at, last_message_at]
$convs = [
    [1001,1000,1073,'2026-03-05 10:00:00','2026-06-01 21:45:00'],
    [1002,1000,1063,'2026-03-10 09:30:00','2026-05-28 20:30:00'],
    [1003,1003,1039,'2026-03-20 17:30:00','2026-05-30 19:00:00'],
    [1004,1003,1051,'2026-04-01 18:00:00','2026-05-25 21:15:00'],
    [1005,1004,1019,'2026-03-15 19:00:00','2026-05-22 20:30:00'],
    [1006,1005,1029,'2026-03-18 17:45:00','2026-06-01 18:45:00'],
    [1007,1000,1001,'2026-03-03 08:00:00','2026-05-30 10:00:00'],
    [1008,1000,1002,'2026-03-03 08:30:00','2026-05-29 09:30:00'],
    [1009,1003,1005,'2026-03-04 09:00:00','2026-05-27 16:00:00'],
    [1010,1000,1007,'2026-04-10 09:00:00','2026-05-28 10:30:00'],
    [1011,1007,1073,'2026-04-20 10:00:00','2026-05-25 11:00:00'],
    [1012,1007,1051,'2026-04-08 09:30:00','2026-05-20 10:00:00'],
    [1013,1000,1008,'2026-04-15 08:00:00','2026-05-26 08:30:00'],
    [1014,1003,1009,'2026-03-20 08:30:00','2026-05-20 09:00:00'],
    [1015,1000,1006,'2026-03-05 09:00:00','2026-05-28 12:00:00'],
    [1016,1004,1011,'2026-03-10 17:30:00','2026-04-20 18:00:00'],
    [1017,1003,1045,'2026-03-25 18:00:00','2026-05-15 19:30:00'],
    [1018,1001,1065,'2026-04-01 09:00:00','2026-05-29 21:30:00'],
    [1019,1000,1075,'2026-04-15 20:30:00','2026-05-30 22:00:00'],
    [1020,1006,1010,'2026-03-10 09:00:00','2026-05-25 10:30:00'],
];

// Messages: [id, conv_id, sender_id, body, created_at, read_at]
$messages = [
    // Conv 1001: Marc Puig ↔ Pol Masferrer (Júnior)
    [1001,1001,1000,"Pol, bon entrenament avui. Com et trobes de les cames?",'2026-05-28 21:00:00','2026-05-28 21:10:00'],
    [1002,1001,1073,"Gràcies míster. Una mica carregat però bé. Descansaré bé aquesta nit.",'2026-05-28 21:15:00','2026-05-28 21:20:00'],
    [1003,1001,1000,"Perfecte. Recorda de fer els estiraments que et va indicar la Marta.",'2026-05-28 21:20:00','2026-05-28 21:25:00'],
    [1004,1001,1073,"Sí, ja ho faré. Míster, vol dir que podré jugar el dissabte?",'2026-05-29 09:00:00','2026-05-29 09:30:00'],
    [1005,1001,1000,"Si et trobes bé a l'entrenament de dijous, sí. Ja ho valorem.",'2026-05-29 09:30:00','2026-05-29 09:45:00'],
    [1006,1001,1073,"Perfecte. Gràcies per tot, míster. A dijous!",'2026-06-01 21:45:00',null],
    // Conv 1002: Marc Puig ↔ Pol Puig (Juvenil)
    [1007,1002,1000,"Pol, volem parlar sobre la teva posició al camp. Pots venir uns minuts abans del proper entrenament?",'2026-05-25 09:00:00','2026-05-25 15:00:00'],
    [1008,1002,1063,"Clar que sí, míster. Dimarts estaré aquí 15 minuts abans.",'2026-05-25 15:30:00','2026-05-25 16:00:00'],
    [1009,1002,1000,"Perfecte. Parlarem del teu posicionament en les transicions defensives. Fins dimarts!",'2026-05-25 16:00:00','2026-05-25 16:30:00'],
    [1010,1002,1063,"Entès, míster. Hi seré. Alguna cosa que hauria de repassar de vídeo?",'2026-05-27 19:00:00','2026-05-27 20:00:00'],
    [1011,1002,1000,"Mira els últims 10 minuts del partit de dijous. Fixa't en la teva posició quan perdem la pilota.",'2026-05-27 20:30:00','2026-05-28 09:00:00'],
    [1012,1002,1063,"Ho he vist. Entenc el que voleu dir. Treballaré-ho.",'2026-05-28 20:30:00',null],
    // Conv 1003: Arnau Vila ↔ Pol Martí (Infantil - lesió)
    [1013,1003,1039,"Hola Pol, com va el genoll? Com et trobes per a l'entrenament de demà?",'2026-05-28 18:00:00','2026-05-28 18:30:00'],
    [1014,1003,1039,"Hola entrenador! Avui millor que ahir. Crec que podré entrenar, però amb cura.",'2026-05-28 18:35:00','2026-05-28 19:00:00'],
    [1015,1003,1003,"Perfecte. No forcis si notes alguna molèstia. Ja parlarem amb la Marta.",'2026-05-28 19:00:00','2026-05-28 19:15:00'],
    [1016,1003,1039,"D'acord. Gràcies entrenador. A demà!",'2026-05-28 19:20:00','2026-05-28 19:30:00'],
    [1017,1003,1003,"A demà! Fes els exercicis de mobilitat que et va dir la fisio.",'2026-05-29 09:00:00','2026-05-29 18:00:00'],
    [1018,1003,1039,"Sí, els he fet tots avui al matí. Em trobo molt millor!",'2026-05-30 19:00:00',null],
    // Conv 1004: Arnau Vila ↔ Roger Puig (Cadet)
    [1019,1004,1003,"Roger, has fet un gran mes de maig. Volem donar-te més responsabilitat al camp la temporada que ve.",'2026-05-23 21:00:00','2026-05-23 22:00:00'],
    [1020,1004,1051,"Moltes gràcies entrenador! Estic molt content. Treballaré molt dur.",'2026-05-23 22:15:00','2026-05-24 08:00:00'],
    [1021,1004,1003,"Ho sé. Et veig molt madur per a la teva edat. Pensem en posar-te de capità la temporada que ve.",'2026-05-24 08:30:00','2026-05-24 09:00:00'],
    [1022,1004,1051,"Seria un honor! Faré tot el possible per merèixer-ho.",'2026-05-24 09:30:00','2026-05-24 10:00:00'],
    [1023,1004,1003,"Ho tinc clar. Segueix treballant com fins ara. Fins dijous!",'2026-05-24 10:00:00','2026-05-24 10:30:00'],
    [1024,1004,1051,"A dijous entrenador! Moltes gràcies per tot.",'2026-05-25 21:15:00',null],
    // Conv 1005: Xavier Puigdomènech ↔ Jordi Batlle (Benjamí)
    [1025,1005,1004,"Hola Jordi! Avui has fet un entrenament excel·lent. La teva sortida de pilota ha millorat moltíssim.",'2026-05-20 20:30:00','2026-05-20 21:00:00'],
    [1026,1005,1019,"Gràcies entrenador! He estat practicant molt a casa.",'2026-05-20 21:00:00','2026-05-20 21:15:00'],
    [1027,1005,1004,"Es nota! Continua practicant els tocs curts. La propera sessió farem circuits específics.",'2026-05-20 21:15:00','2026-05-20 21:30:00'],
    [1028,1005,1019,"D'acord! Fins dilluns entrenador.",'2026-05-21 09:00:00','2026-05-21 10:00:00'],
    [1029,1005,1004,"Fins dilluns! I recorda d'arribar 5 minuts abans per a l'escalfament.",'2026-05-21 10:00:00','2026-05-21 11:00:00'],
    [1030,1005,1019,"Ho recordaré! Que passi un bon cap de setmana.",'2026-05-22 20:30:00',null],
    // Conv 1006: Laia Ferrer ↔ Sergi Mas (Aleví)
    [1031,1006,1005,"Sergi, avui has estat brillant entre els pals. Estàs millorant molt.",'2026-06-01 18:30:00','2026-06-01 18:45:00'],
    [1032,1006,1029,"Gràcies Laia! Estic molt motivat amb l'entrenament de porters.",'2026-06-01 18:50:00','2026-06-01 19:00:00'],
    [1033,1006,1005,"Es nota molt! Dimarts treballarem les eixides en les pilotes aèries específicament.",'2026-06-01 19:00:00','2026-06-01 19:15:00'],
    [1034,1006,1029,"Perfecte! És el que més necessito millorar.",'2026-06-01 19:20:00','2026-06-01 19:25:00'],
    [1035,1006,1005,"Exacte, ho has identificat molt bé. Fins dimarts!",'2026-06-01 19:25:00','2026-06-01 19:30:00'],
    [1036,1006,1029,"Fins dimarts! Bona tarda.",'2026-06-01 18:45:00',null],
    // Conv 1007: Marc Puig ↔ Jordi Fernández (coordinació tècnica)
    [1037,1007,1000,"Jordi, hem de parlar del programa de pre-temporada per als categories superiors. Pots enviar-me el teu pla?",'2026-05-28 09:00:00','2026-05-28 09:30:00'],
    [1038,1007,1001,"Clar Marc. Te l'envio avui a la tarda. Havia pensat en 3 setmanes de condicionament base.",'2026-05-28 09:35:00','2026-05-28 10:00:00'],
    [1039,1007,1000,"Perfecte. Inclou exercicis específics de velocitat per als davanters. L'any passat ens va fallar la fase final.",'2026-05-28 10:00:00','2026-05-28 10:30:00'],
    [1040,1007,1001,"Entès. Dissenyaré circuits de velocitat explosiva i resistència específica per posicions.",'2026-05-28 10:30:00','2026-05-28 11:00:00'],
    [1041,1007,1000,"Molt bé. Quedarem divendres per revisar-ho junts?",'2026-05-29 09:00:00','2026-05-29 09:30:00'],
    [1042,1007,1001,"Perfecte, divendres a les 9:00 al despatx. Ho tindré llest.",'2026-05-30 10:00:00',null],
    // Conv 1008: Marc Puig ↔ Pau Soler (porters)
    [1043,1008,1000,"Pau, com veus als porters de la pedrera? Algun amb projecció per al primer equip?",'2026-05-27 09:00:00','2026-05-27 09:30:00'],
    [1044,1008,1002,"En Pol Masferrer és clarament el millor. Reflexos, anticipació i joc de peus excel·lents per la seva edat.",'2026-05-27 09:45:00','2026-05-27 10:00:00'],
    [1045,1008,1000,"Ho pensava jo també. I el Sergi Mas de l'aleví?",'2026-05-27 10:00:00','2026-05-27 10:30:00'],
    [1046,1008,1002,"Té molt potencial però li falta temps. Potser d'aquí 3-4 anys. Estic fent treball específic amb ell.",'2026-05-27 10:30:00','2026-05-27 11:00:00'],
    [1047,1008,1000,"Molt bé. Segueix amb ell. Si pot pujar de categoria l'any vinent seria ideal.",'2026-05-28 09:00:00','2026-05-28 09:30:00'],
    [1048,1008,1002,"D'acord. Li mantindré un programa personalitzat de porters.",'2026-05-29 09:30:00',null],
    // Conv 1009: Arnau Vila ↔ Laia Ferrer (tècnica)
    [1049,1009,1003,"Laia, has vist la millora de l'aleví en el pressing? Crec que les teves sessions tècniques estan donant fruits.",'2026-05-25 16:00:00','2026-05-25 16:30:00'],
    [1050,1009,1005,"Sí! Estic molt contenta. L'Iker i la Mireia han millorat especialment.",'2026-05-25 16:35:00','2026-05-25 17:00:00'],
    [1051,1009,1003,"Exacte. Podríem fer un treball conjunt entre l'aleví i l'infantil? Una sessió mixta de tècnica?",'2026-05-25 17:00:00','2026-05-25 17:30:00'],
    [1052,1009,1005,"M'encanta la idea! Ho podem fer el divendres 13 de juny. Tenim els dos camps disponibles.",'2026-05-26 09:00:00','2026-05-26 09:30:00'],
    [1053,1009,1003,"Perfecte, ho apunto. Avises en Roger per coordinar els espais?",'2026-05-26 09:30:00','2026-05-26 10:00:00'],
    [1054,1009,1005,"Ja ho dic jo. Genial, serà una sessió molt productiva!",'2026-05-27 16:00:00',null],
    // Conv 1010: Marc Puig ↔ Marta Bosch (fisio)
    [1055,1010,1007,"Marc, tinc cura especial amb en Joel Batlle (Cadet). El turmell millora però necessita 2 setmanes més sense joc real.",'2026-05-26 08:30:00','2026-05-26 09:00:00'],
    [1056,1010,1000,"Entès Marta. El mantindré en sessions de gimnàs amb en Jordi. Res de camp fins que tu diguis.",'2026-05-26 09:00:00','2026-05-26 09:30:00'],
    [1057,1010,1007,"Perfecte. L'Arnau Batlle del juvenil també el controlo per la pronació. Res alarmant però vigilem.",'2026-05-26 09:30:00','2026-05-26 10:00:00'],
    [1058,1010,1000,"Gràcies per l'avís. Hauré de limitar els seus salts en els propers entrenaments?",'2026-05-27 09:00:00','2026-05-27 09:30:00'],
    [1059,1010,1007,"Millor que sí. Evitar exercicis de salts repetitius. Passa'm el planning de la setmana i marco quins han d'adaptar.",'2026-05-27 09:30:00','2026-05-27 10:00:00'],
    [1060,1010,1000,"Te'l passo avui. Gràcies Marta, com sempre!",'2026-05-28 10:30:00',null],
    // Conv 1011: Marta Bosch ↔ Pol Masferrer (recuperació)
    [1061,1011,1007,"Pol, com et trobes el turmell avui? L'entrenador m'ha dit que notaves molèstia ahir.",'2026-05-23 10:00:00','2026-05-23 10:30:00'],
    [1062,1011,1073,"Hola Marta! Avui millor. Ahir estava una mica inflamat però he posat gel i he descansat.",'2026-05-23 10:45:00','2026-05-23 11:00:00'],
    [1063,1011,1007,"Bé. Vine demà uns 20 minuts abans de l'entrenament i et faré una valoració ràpida.",'2026-05-23 11:00:00','2026-05-23 11:30:00'],
    [1064,1011,1073,"D'acord Marta. Hi seré. Gràcies!",'2026-05-23 11:30:00','2026-05-23 12:00:00'],
    [1065,1011,1007,"Perfecte. Porta equipament complet per si pots entrenar normal després.",'2026-05-24 09:00:00','2026-05-24 09:30:00'],
    [1066,1011,1073,"Perfecte! Hi estaré.",'2026-05-25 11:00:00',null],
    // Conv 1012: Marta Bosch ↔ Roger Puig (turmell)
    [1067,1012,1007,"Roger, com va la lesió? L'entrenador diu que entrenaves amb certa molèstia ahir.",'2026-05-18 09:30:00','2026-05-18 10:00:00'],
    [1068,1012,1051,"Hola Marta! El turmell ja no em fa mal. Crec que estic totalment bé.",'2026-05-18 10:15:00','2026-05-18 10:30:00'],
    [1069,1012,1007,"Bé, però anem amb compte. Vine divendres i et faig una revisió completa.",'2026-05-18 10:30:00','2026-05-18 11:00:00'],
    [1070,1012,1051,"D'acord. Divendres a quina hora?",'2026-05-19 09:00:00','2026-05-19 09:30:00'],
    [1071,1012,1007,"A les 17:30 al gimnàs, just abans de l'entrenament del Júnior.",'2026-05-19 09:30:00','2026-05-19 10:00:00'],
    [1072,1012,1051,"Perfecte, hi estaré. Gràcies Marta!",'2026-05-20 10:00:00',null],
    // Conv 1013: Marc Puig ↔ Enric Llopis (delegat)
    [1073,1013,1008,"Marc, he reservat dos autobusos per als últims dos partits de fora. Confirmes el nombre de jugadors?",'2026-05-24 08:00:00','2026-05-24 08:30:00'],
    [1074,1013,1000,"Per al primer equip som 18 jugadors + 4 staff. Per al Júnior B 16 + 3 staff.",'2026-05-24 08:30:00','2026-05-24 09:00:00'],
    [1075,1013,1008,"Perfecte. L'autobús gran té 54 places, podem anar tots junts si vols.",'2026-05-24 09:00:00','2026-05-24 09:30:00'],
    [1076,1013,1000,"Millor anar junts, més econòmic. Reserva per a 45 persones.",'2026-05-25 08:00:00','2026-05-25 08:30:00'],
    [1077,1013,1008,"Fet. Surtirem a les 9:30 del Camp Principal. Ho comunico als equips?",'2026-05-25 08:30:00','2026-05-25 09:00:00'],
    [1078,1013,1000,"Sí, envia la informació per la plataforma a tots els convocats.",'2026-05-26 08:30:00',null],
    // Conv 1014: Arnau Vila ↔ Toni Gallart (utiller)
    [1079,1014,1009,"Arnau, falta material al Camp B: 3 porteries de minicamp s'han espatllat. Necessitem solució urgentament.",'2026-05-18 08:30:00','2026-05-18 09:00:00'],
    [1080,1014,1003,"Gràcies per l'avís Toni. Comprova si les del magatzem es poden usar mentre.",'2026-05-18 09:00:00','2026-05-18 09:30:00'],
    [1081,1014,1009,"Ho he mirat i dues estan en bon estat. Però no en tenim prou per a dos camps simultanis.",'2026-05-18 09:30:00','2026-05-18 10:00:00'],
    [1082,1014,1003,"D'acord. Posa-ho a la llista de compres de la plataforma, prioritat alta.",'2026-05-19 08:30:00','2026-05-19 09:00:00'],
    [1083,1014,1009,"Ja ho he fet. He posat el link a Amazon, cada porteria val uns 45€.",'2026-05-19 09:00:00','2026-05-19 09:30:00'],
    [1084,1014,1003,"Perfecte. Ho valorem amb l'administració.",'2026-05-20 09:00:00',null],
    // Conv 1015: Marc Puig ↔ Roger Mas (coordinació)
    [1085,1015,1006,"Marc, he elaborat un informe de rendiment de totes les categories. Te'l puc enviar?",'2026-05-26 09:00:00','2026-05-26 09:30:00'],
    [1086,1015,1000,"Clar Roger, seria molt útil. Especialment per a la reunió amb la junta del 15 de juny.",'2026-05-26 09:30:00','2026-05-26 10:00:00'],
    [1087,1015,1006,"Perfecte. Incluiré: assistència, resultats, progressió individual i proposta de canvis per a l'any vinent.",'2026-05-27 08:30:00','2026-05-27 09:00:00'],
    [1088,1015,1000,"Afegeix també la proposta d'ascens de categoria per a alguns jugadors de la pedrera.",'2026-05-27 09:00:00','2026-05-27 09:30:00'],
    [1089,1015,1006,"Ja ho tenia contemplat. Tinc 5 jugadors candidats a pujar. Ho detallaré.",'2026-05-28 08:30:00','2026-05-28 09:00:00'],
    [1090,1015,1000,"Excel·lent feina Roger. Ens veiem el divendres abans de la junta.",'2026-05-28 12:00:00',null],
    // Conv 1016: Xavier Puigdomènech ↔ Biel Puig (Prebenjamí - primer mes)
    [1091,1016,1004,"Hola Biel! Quin bon primer entrenament has fet avui. Com t'has sentit?",'2026-03-11 18:30:00','2026-03-11 19:00:00'],
    [1092,1016,1011,"Molt bé entrenador! M'ha agradat molt.",'2026-03-12 09:00:00','2026-03-12 10:00:00'],
    [1093,1016,1004,"Molt bé! Practica a casa el toc de pilota amb el peu esquerre. El teu dret ja és molt bo.",'2026-03-12 10:00:00','2026-03-12 11:00:00'],
    [1094,1016,1011,"D'acord! Ho practicaré cada dia.",'2026-03-13 09:00:00','2026-03-13 10:00:00'],
    [1095,1016,1004,"Perfecte Biel! Fins dilluns que ve.",'2026-04-18 17:30:00','2026-04-18 18:00:00'],
    [1096,1016,1011,"Fins dilluns entrenador!",'2026-04-20 18:00:00',null],
    // Conv 1017: Arnau Vila ↔ Gerard Riera (Infantil)
    [1097,1017,1003,"Gerard, vull que siguis el responsable de l'escalfament la propera sessió. Prepara 10 minuts d'exercicis.",'2026-05-13 19:30:00','2026-05-13 20:00:00'],
    [1098,1017,1045,"Entès entrenador! M'ho preparo bé. Puc usar els exercicis que hem fet al circuit?",'2026-05-13 20:30:00','2026-05-13 21:00:00'],
    [1099,1017,1003,"Exacte, usa els circuits que coneixem. Afegeix-hi una activació específica de turmells i genolls.",'2026-05-14 08:30:00','2026-05-14 09:00:00'],
    [1100,1017,1045,"D'acord! Ho tinc molt clar.",'2026-05-14 09:30:00','2026-05-14 10:00:00'],
    [1101,1017,1003,"Molt bé Gerard. Estàs demostrant qualitats de lideratge importants.",'2026-05-14 10:00:00','2026-05-14 10:30:00'],
    [1102,1017,1045,"Moltes gràcies entrenador. Intento aprendre molt de vosaltres.",'2026-05-15 19:30:00',null],
    // Conv 1018: Jordi Fernández ↔ Marc Mas (Juvenil)
    [1103,1018,1001,"Marc, com va el programa de força? Notes millora en la resistència als darrers minuts dels partits?",'2026-05-27 09:00:00','2026-05-27 09:30:00'],
    [1104,1018,1065,"Sí Jordi! Noto que em canso menys als darrers 20 minuts. El programa funciona bé.",'2026-05-27 09:45:00','2026-05-27 10:00:00'],
    [1105,1018,1001,"Excel·lent. La setmana que ve pujo lleugerament la intensitat. Podràs?",'2026-05-27 10:00:00','2026-05-27 10:30:00'],
    [1106,1018,1065,"Sí, sense problema. Estic molt motivat amb el treball físic.",'2026-05-28 09:00:00','2026-05-28 09:30:00'],
    [1107,1018,1001,"Molt bé. Si tot va bé el mes de juny et proposo un treball personalitzat de pre-temporada.",'2026-05-28 09:30:00','2026-05-28 10:00:00'],
    [1108,1018,1065,"Seria genial! Moltes gràcies Jordi.",'2026-05-29 21:30:00',null],
    // Conv 1019: Marc Puig ↔ Marc Batlle (Júnior)
    [1109,1019,1000,"Marc, hem de parlar sobre la teva renovació per a la temporada que ve. Tens 5 minuts demà?",'2026-05-28 20:00:00','2026-05-28 20:30:00'],
    [1110,1019,1075,"Clar míster! Estaré disponible quan vulgui.",'2026-05-28 20:35:00','2026-05-28 21:00:00'],
    [1111,1019,1000,"Demà a les 19:30 al despatx, just abans de l'entrenament.",'2026-05-28 21:00:00','2026-05-28 21:15:00'],
    [1112,1019,1075,"Perfecte, hi estaré. Puc saber si va bé la renovació?",'2026-05-29 09:00:00','2026-05-29 09:30:00'],
    [1113,1019,1000,"Va molt bé :) Però millor parlem-ho en persona.",'2026-05-29 09:30:00','2026-05-29 10:00:00'],
    [1114,1019,1075,"Perfecte! Moltes gràcies míster. Fins demà.",'2026-05-30 22:00:00',null],
    // Conv 1020: Roger Mas ↔ Núria Sala (inscripcions)
    [1115,1020,1006,"Núria, quants jugadors nous hem rebut a la plataforma aquest mes?",'2026-05-23 09:00:00','2026-05-23 09:30:00'],
    [1116,1020,1010,"Hola Roger! Hem rebut 8 formularis d'inscripció nous. 5 confirmats, 3 pendents de documentació.",'2026-05-23 09:45:00','2026-05-23 10:00:00'],
    [1117,1020,1006,"Molt bé. Pots enviar recordatori als 3 pendents? El termini és el 30 de juny.",'2026-05-23 10:00:00','2026-05-23 10:30:00'],
    [1118,1020,1010,"Ja els he enviat un email avui al matí. Espero resposta en 48h.",'2026-05-24 09:00:00','2026-05-24 09:30:00'],
    [1119,1020,1006,"Perfecte Núria. Com sempre, molt eficient!",'2026-05-24 09:30:00','2026-05-24 10:00:00'],
    [1120,1020,1010,"Gràcies Roger! Si no responen en 48h els trucaem directament.",'2026-05-25 10:30:00',null],
];

// Notifications: [id, sender_id, type, title, body, created_at]
$notifs = [
    [1001,2,'group','Inici de temporada 2025/2026','Benvinguts a la nova temporada! Us informem dels horaris i calendari de totes les categories. Molt bona temporada a tothom!','2026-03-02 09:00:00'],
    [1002,2,'group',"Canvi d'horari Benjamí A","A partir del 15 de març, els entrenaments del Benjamí A seran els dilluns i dimecres de 18:30 a 20:00 h al Camp B.",'2026-03-10 10:00:00'],
    [1003,1000,'group','Convocatòria Torneig Prebenjamí','El dissabte 22 de març tenim el Torneig Local Prebenjamí al Camp Municipal. Presentació a les 9:00 h. Material requerit: equipament complet.','2026-03-18 12:00:00'],
    [1004,2,'group','Actualització de bonos temporada','Recordem que els bonos de la temporada es poden renovar a partir del mes d\'abril. Consulteu les noves tarifes a la plataforma.','2026-04-01 09:00:00'],
    [1005,1000,'group','Torneig de Pasqua - Infantil A','Torneig de Pasqua el diumenge 13 d\'abril. Presentació a les 10:00 h al Camp Principal. Portem equipament complet.','2026-04-07 15:00:00'],
    [1006,1000,'individual','Recordatori: Avaluació de rendiment','Recordem als jugadors de les categories Infantil, Cadet, Juvenil i Júnior que les avaluacions de rendiment es faran la setmana del 13 al 16 de maig.','2026-05-08 10:00:00'],
    [1007,2,'group','Festa de final de temporada','La Festa de Final de Temporada tindrà lloc el 28 de juny al Camp Principal. Més informació properament!','2026-05-15 11:00:00'],
    [1008,1000,'group','Canvi de camp - Cadet A','Per reformes al Camp B, els entrenaments del Cadet A es faran al Camp Principal fins a nova ordre.','2026-05-20 09:00:00'],
    [1009,1003,'individual','Protocol de recuperació per a jugador lesionat',"S'ha establert un protocol de recuperació per a en Joel Batlle. La fisioterapeuta Marta farà seguiment personalitzat.",'2026-04-10 10:30:00'],
    [1010,2,'group','Renovació d\'inscripcions 2026/2027','Ja es pot formalitzar la inscripció per a la temporada 2026/2027. El termini per als actuals jugadors és el 30 de juny.','2026-05-25 09:00:00'],
    [1011,1006,'group','Nou portal de documentació','Ja teniu disponible la nova secció de documentació a la plataforma. Trobareu fitxes tècniques, reglaments i documents administratius.','2026-04-20 11:00:00'],
    [1012,1000,'individual','Incorporació temporal al Primer Equip','Informem de la incorporació temporal d\'un jugador de la pedrera al primer equip per al proper cicle de competició.','2026-05-10 12:00:00'],
    [1013,2,'group','Mèdics i assegurances','Recordem que tots els jugadors han de tenir al dia la targeta federativa i l\'assegurança esportiva. Consulteu el delegat si teniu dubtes.','2026-03-20 10:00:00'],
    [1014,1001,'group','Programa de preparació física d\'estiu','Per als jugadors de categories superiors, hem preparat un programa de preparació física per a l\'estiu. Consulteu el preparador físic.','2026-05-28 09:00:00'],
    [1015,2,'group','Benvinguda a la plataforma digital','Us donem la benvinguda a la plataforma digital de l\'acadèmia. Aquí trobareu tota la informació: horaris, notes, bonos i comunicació directa amb els entrenadors.','2026-03-02 08:00:00'],
    [1016,1000,'individual','Selecció provincial - Felicitació','Comunicar que en Marc Mas (Juvenil A) ha estat convocat per a la selecció provincial sub-18. Enhorabona Marc!','2026-05-05 13:00:00'],
    [1017,1006,'group','Calendari - Darrers partits de temporada','Us adjuntem el calendari dels darrers partits de la temporada regular per a totes les categories. Cal confirmar assistència a la plataforma.','2026-05-18 10:00:00'],
    [1018,2,'group','Tancament temporal instal·lacions','Les instal·lacions estaran tancades del 28 d\'agost al 4 de setembre per manteniment anual. Represa: 7 de setembre.','2026-06-01 09:00:00'],
];

// notification_recipients [id, notif_id, recipient_id, read_at]
// For group notifs → all coaches + staff + sample players; individual → specific
$notifRecipients = [];
$nrId = 1001;
$allCoachStaffIds = array_column($coaches, 0);
foreach ($staff as $s) $allCoachStaffIds[] = $s[0];
$samplePlayerIds = [1039,1045,1051,1057,1063,1069,1073,1079,1029,1019,1011];

foreach ($notifs as $n) {
    [$nid,,,$type] = $n;
    if ($type === 'group') {
        $rcpts = array_merge($allCoachStaffIds, $samplePlayerIds);
    } else {
        // Individual notifications go to specific people
        $rcpts = match($nid) {
            1006 => [1039,1043,1051,1055,1063,1067,1073,1077],
            1009 => [1003,1007,1059],
            1012 => [1000,1006,1073],
            1016 => [1000,1006,1065],
            default => [1000,1006],
        };
    }
    $readBase = ['2026-03-05 10:00:00','2026-03-15 09:00:00','2026-04-02 08:30:00','2026-04-22 10:00:00','2026-05-09 09:00:00'];
    $ri = 0;
    foreach ($rcpts as $rid) {
        $readAt = ($ri % 3 === 0) ? null : $readBase[$ri % count($readBase)];
        $notifRecipients[] = [$nrId++, $nid, $rid, $readAt];
        $ri++;
    }
}

// Purchase requests
$purchases = [
    [1001,'Pilotes de futbol Mikasa FL550','Necessitem 10 pilotes noves per als entrenaments de categories base. Les actuals estan molt desgastades.',null,180.00,'material_deportivo','alta','comprado',"Aprovat i comprat. Les pilotes ja han arribat al magatzem.",1008,2,'2026-03-10 10:00:00','2026-03-05 11:00:00','2026-03-15 09:00:00'],
    [1002,'Xarxes de porteria (parell)','Les xarxes de la porteria del Camp B estan trencades. Cal substituir-les urgentment.',null,120.00,'instalaciones','alta','comprado',"Comprat. Instal·lades el 20 de març.",1009,2,'2026-03-12 09:00:00','2026-03-10 08:30:00','2026-03-22 10:00:00'],
    [1003,'Petos d\'entrenament (20 unitats)','Per diferenciar equips als entrenaments necessitem petos de colors. Set de 20 unitats.',null,65.00,'equipamiento','media','aprobado',"Aprovat. Pendent de comanda.",1003,2,'2026-03-25 10:00:00','2026-03-20 12:00:00','2026-03-26 09:00:00'],
    [1004,'Cronòmetre professional','El cronòmetre actual s\'ha espatllat. Necessitem un de nou per mesurar temps als entrenaments físics.',null,45.00,'tecnologia','media','aprobado',"Aprovat. Pendent de compra.",1001,2,'2026-04-02 09:00:00','2026-04-01 10:00:00','2026-04-03 08:30:00'],
    [1005,'Cons de senyalitzar (50 unitats)','Necessitem cons per als circuits d\'entrenament. Demanem 50 unitats de colors variats.',null,35.00,'material_deportivo','baja','pendiente',null,1005,null,null,'2026-04-10 10:00:00','2026-04-10 10:00:00'],
    [1006,'Màquines de fitness multifuncionals (2 u.)','Per millorar la sala de preparació física, proposem la compra de 2 màquines de fitness.',null,2800.00,'instalaciones','media','denegado',"Per ara el pressupost no permet aquesta inversió. Ho revisarem per a la temporada vinent.",1001,2,'2026-04-15 09:00:00','2026-04-12 10:00:00','2026-04-16 09:00:00'],
    [1007,'Dron per gravació de partits','Proposem adquirir un dron per poder gravar els partits des de dalt i fer anàlisi tàctica.',null,950.00,'tecnologia','baja','denegado',"No prioritari. Podem utilitzar càmera fixa per a l'anàlisi de vídeo.",1000,2,'2026-04-20 11:00:00','2026-04-18 10:00:00','2026-04-22 09:00:00'],
    [1008,'Roba tècnica estiuenca (equip complet)','Per a la propera temporada, cal renovar la roba tècnica. Preu estimat: 85€ × 15 entrenadors/staff.',null,1275.00,'equipamiento','media','en_revision',null,1010,null,null,'2026-05-05 10:00:00','2026-05-05 10:00:00'],
    [1009,'Tablets per als entrenadors (4 unitats)','Per visualitzar vídeo i estadístiques al camp durant els entrenaments, proposem 4 tablets resistents.',null,1200.00,'tecnologia','media','en_revision',null,1006,null,null,'2026-05-10 09:00:00','2026-05-10 09:00:00'],
    [1010,'Botiquí mèdic complet','Cal renovar el material del botiquí. La fisioterapeuta ha elaborat una llista detallada del material.',null,180.00,'material_deportivo','alta','pendiente',null,1007,null,null,'2026-05-20 08:30:00','2026-05-20 08:30:00'],
    [1011,'Gespa artificial premium (Camp B)','Proposem renovar la gespa del Camp B per una de qualitat superior per reduir lesions.',null,45000.00,'instalaciones','baja','denegado',"La inversió és massa gran per a l'exercici actual. Planificarem per a 2027.",1003,2,'2026-04-08 09:00:00','2026-04-05 11:00:00','2026-04-09 09:00:00'],
    [1012,'Pilotes específiques per a porters (10 u.)','Per als entrenaments de porters, calen pilotes específiques de tir.',null,220.00,'material_deportivo','media','pendiente',null,1002,null,null,'2026-06-01 10:00:00','2026-06-01 10:00:00'],
];

// ---------------------------------------------------------------------------
// GENERATE SQL HEADER
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- SEED DATA — Academia JP Fútbol");
out("-- Generat: " . TODAY . " | Password per a tots: Joma2026!");
out("-- Ús: mysql -u root -p jp_preparation < seed_data.sql");
out("-- O des del contenidor: docker exec -i jp_db mysql -u root -pQwaszx12345_ jp_preparation < seed_data.sql");
out("-- ============================================================");
out("-- NOTA: Tots els IDs nous comencen des de 1000+ per evitar");
out("--       conflictes amb dades existents.");
out("-- ============================================================");
out();
out("SET FOREIGN_KEY_CHECKS = 0;");
out();

// ---------------------------------------------------------------------------
// 1. LOCATIONS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 1. LOCATIONS");
out("-- ============================================================");
out("INSERT INTO locations (id, name, description, address, type, capacity, phone, active, created_at, updated_at) VALUES");
$rows = [];
foreach ($locations as [$id,$name,$desc,$addr,$type,$cap,$phone]) {
    $rows[] = "({$id}, " . q($name) . ", " . q($desc) . ", " . q($addr) . ", '{$type}', {$cap}, " . q($phone) . ", 1, '2026-03-01 08:00:00', '2026-03-01 08:00:00')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 2. USERS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 2. USERS (coaches, staff, players)");
out("-- ============================================================");
$ts = '2026-03-02 09:00:00';
out("INSERT INTO users (id, name, email, password, role, staff_title, status, welcomed_at, created_at, updated_at) VALUES");
$rows = [];
foreach ($coaches as [$id,$name,$email,$title]) {
    $rows[] = "({$id}, " . q($name) . ", " . q($email) . ", '" . HASH . "', 'coach', " . q($title) . ", 'active', '2026-03-02 09:00:00', '{$ts}', '{$ts}')";
}
foreach ($staff as [$id,$name,$email,$title]) {
    $rows[] = "({$id}, " . q($name) . ", " . q($email) . ", '" . HASH . "', 'staff', " . q($title) . ", 'active', '2026-03-02 09:00:00', '{$ts}', '{$ts}')";
}
foreach ($players as $p) {
    [$id,$name,$email] = $p;
    $rows[] = "({$id}, " . q($name) . ", " . q($email) . ", '" . HASH . "', 'player', NULL, 'active', NULL, '{$ts}', '{$ts}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 3. PLAYER PROFILES
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 3. PLAYER PROFILES");
out("-- ============================================================");
out("INSERT INTO player_profiles (id, player_id, birth_date, height, weight, position, level, category, team, league, medical_notes, created_at, updated_at) VALUES");
$rows = [];
$ppId = 1001;
foreach ($players as $p) {
    [$id,$name,$email,$birth,$pos,$level,$cat,$team,$league,$h,$w,$med] = $p;
    $rows[] = "({$ppId}, {$id}, '{$birth}', {$h}, {$w}, " . q($pos) . ", '{$level}', '{$cat}', " . q($team) . ", " . q($league) . ", " . q($med) . ", '{$ts}', '{$ts}')";
    $ppId++;
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 4. BONO TYPES
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 4. BONO TYPES");
out("-- ============================================================");
out("INSERT INTO bono_types (id, name, sessions, price, validity_days, active, created_at, updated_at) VALUES");
$rows = [];
foreach ($bonoTypes as [$id,$name,$sess,$price,$val,$act]) {
    $rows[] = "({$id}, " . q($name) . ", {$sess}, " . qf($price) . ", {$val}, {$act}, '{$ts}', '{$ts}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 5. PLAYER BONOS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 5. PLAYER BONOS");
out("-- ============================================================");
out("INSERT INTO player_bonos (id, player_id, bono_type_id, sessions_total, sessions_remaining, start_date, expires_at, notes, created_by, created_at, updated_at) VALUES");
$rows = [];
foreach ($playerBonos as [$id,$pid,$btid,$total,$rem,$start,$exp,$notes]) {
    $rows[] = "({$id}, {$pid}, {$btid}, {$total}, {$rem}, '{$start}', '{$exp}', " . q($notes) . ", 2, '{$start} 09:00:00', '{$start} 09:00:00')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 6. CLASSES
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 6. CLASSES (plantilles recurrents)");
out("-- ============================================================");
$dayMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
out("INSERT INTO classes (id, title, description, type, recurrence_days, recurrence_start, recurrence_end, recurrence_time_start, recurrence_time_end, default_location_id, default_focus, created_by, created_at, updated_at) VALUES");
$rows = [];
foreach ($classDefs as $cid => [$title, $days, $sStart, $sEnd, $locId, $coachArr, $pStart, $pEnd]) {
    $dayStr = implode(',', array_map(fn($d) => $dayMap[$d], $days));
    $mainCoach = $coachArr[0];
    $rows[] = "({$cid}, " . q($title) . ", " . q("Entrenaments {$title} - Temporada 2025/2026") . ", 'recurring', '{$dayStr}', '2026-03-02', '2026-07-31', '{$sStart}', '{$sEnd}', {$locId}, NULL, {$mainCoach}, '2026-03-01 10:00:00', '2026-03-01 10:00:00')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 7-9. CLASS SESSIONS + COACHES + PLAYERS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 7. CLASS SESSIONS");
out("-- ============================================================");

$sessionRows  = [];
$cscRows      = [];
$cspRows      = [];
$sessionId    = 1001;
$cscId        = 1001;
$cspId        = 1001;

$rangeStart = new DateTime(RANGE_START);
$rangeEnd   = new DateTime(RANGE_END);
$todayDt    = new DateTime(TODAY);
$cur        = clone $rangeStart;

// Seed for pseudo-random post_notes per session
$focusIdx = 0;
$pnIdx    = 0;

while ($cur <= $rangeEnd) {
    $dow     = (int)$cur->format('N'); // 1=Mon ... 7=Sun
    $dateStr = $cur->format('Y-m-d');
    $isPast  = $cur < $todayDt;

    foreach ($classDefs as $cid => [$title, $days, $sStart, $sEnd, $locId, $coachArr, $pStart, $pEnd]) {
        if (!in_array($dow, $days)) continue;

        $status    = $isPast ? 'completed' : 'scheduled';
        $mainCoach = $coachArr[0];
        $focus     = $focuses[$focusIdx % count($focuses)];
        $focusIdx++;

        // post_notes only for past sessions
        $postNote = null;
        if ($isPast) {
            $postNote = $postNotes[$pnIdx % count($postNotes)];
            $pnIdx++;
        }

        $listaPasadaAt  = $isPast ? "'{$dateStr} " . substr($sEnd, 0, 5) . ":00'" : 'NULL';
        $listaPasadaBy  = $isPast ? $mainCoach : 'NULL';
        $createdAt      = $dateStr < '2026-03-04' ? '2026-03-01 10:00:00' : $dateStr . ' 08:00:00';

        $sessionRows[] = "({$sessionId}, {$cid}, " . q($title) . ", '{$dateStr}', '{$sStart}', '{$sEnd}', {$locId}, NULL, " . q($focus) . ", NULL, " . q($postNote) . ", {$listaPasadaAt}, {$listaPasadaBy}, '{$status}', {$mainCoach}, '{$createdAt}', '{$createdAt}')";

        // Class session coaches
        foreach ($coachArr as $coachId) {
            $cscRows[] = "({$cscId}, {$sessionId}, {$coachId}, '{$dateStr} 08:00:00')";
            $cscId++;
        }

        // Class session players
        for ($pid = $pStart; $pid <= $pEnd; $pid++) {
            $attendance = att($pid, $dateStr, $isPast);

            // responded_at
            $respondedAt = 'NULL';
            if ($isPast && in_array($attendance, ['present','absent'])) {
                $respondedAt = "'{$dateStr} {$sStart}'";
            } elseif (!$isPast && $attendance === 'confirmed') {
                // responded a day or two before
                $rDt = clone $cur; $rDt->modify('-1 day');
                $respondedAt = "'" . $rDt->format('Y-m-d') . " 09:00:00'";
            } elseif (!$isPast && $attendance === 'declined') {
                $rDt = clone $cur; $rDt->modify('-2 days');
                $respondedAt = "'" . $rDt->format('Y-m-d') . " 18:00:00'";
            }

            // bono_deducted_at: set for past 'present' sessions for players with active bonos
            $bonoDeductedAt = 'NULL';
            if ($isPast && $attendance === 'present' && in_array($pid, $activeBonoPids)) {
                // Only for sessions in the last 2 months (after 2026-04-01)
                if ($dateStr >= '2026-04-01') {
                    $h = (int)substr($sStart, 0, 2);
                    $deductTime = sprintf('%02d:%02d:%02d', $h, 30, 0);
                    $bonoDeductedAt = "'{$dateStr} {$deductTime}'";
                }
            }

            // post_obs for some past present sessions (coaches)
            $postObs = 'NULL';
            if ($isPast && $attendance === 'present') {
                $obsHash = abs(crc32($pid . $dateStr . 'obs')) % 10;
                if ($obsHash === 0) {
                    $postObs = q('Bona sessió avui. Millora notable en el posicionament.');
                } elseif ($obsHash === 1) {
                    $postObs = q("Cal insistir en la tècnica de recepció. Repetir-ho la setmana que ve.");
                }
            }

            $cspRows[] = "({$cspId}, {$sessionId}, {$pid}, {$mainCoach}, '{$attendance}', NULL, NULL, NULL, {$bonoDeductedAt}, NULL, NULL, {$postObs}, {$respondedAt}, '{$dateStr} 08:00:00', '{$dateStr} 08:00:00')";
            $cspId++;
        }

        $sessionId++;
    }
    $cur->modify('+1 day');
}

// Flush sessions in chunks to avoid huge single INSERT
$chunkSize = 50;
$chunks = array_chunk($sessionRows, $chunkSize);
$first = true;
foreach ($chunks as $chunk) {
    if ($first) { $first = false; }
    out("INSERT INTO class_sessions (id, class_id, title, session_date, start_time, end_time, location_id, location_custom, focus, pre_notes, post_notes, lista_pasada_at, lista_pasada_by, status, created_by, created_at, updated_at) VALUES");
    out(implode(",\n", $chunk) . ";");
    out();
}

out("-- ============================================================");
out("-- 8. CLASS SESSION COACHES");
out("-- ============================================================");
$chunks = array_chunk($cscRows, 100);
foreach ($chunks as $chunk) {
    out("INSERT INTO class_session_coaches (session_id, user_id, created_at) VALUES");
    // Strip the id column value from each row (id is 1st value)
    $fixedChunk = array_map(function($row) {
        // row format: (id, session_id, user_id, datetime)
        // Remove first value (id)
        return preg_replace('/^\(\d+,\s*/', '(', $row);
    }, $chunk);
    out(implode(",\n", $fixedChunk) . ";");
    out();
}

out("-- ============================================================");
out("-- 9. CLASS SESSION PLAYERS");
out("-- ============================================================");
$chunks = array_chunk($cspRows, 100);
foreach ($chunks as $chunk) {
    out("INSERT INTO class_session_players (id, session_id, user_id, coach_id, attendance, absence_reason, student_note, student_noted_at, bono_deducted_at, absence_notes, pre_obs, post_obs, responded_at, created_at, updated_at) VALUES");
    out(implode(",\n", $chunk) . ";");
    out();
}

// ---------------------------------------------------------------------------
// 10. PLAYER ANNOTATIONS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 10. PLAYER ANNOTATIONS");
out("-- ============================================================");
out("INSERT INTO player_annotations (id, player_id, author_id, type, content, document_id, created_at, updated_at) VALUES");
$rows = [];
foreach ($annotations as [$id,$pid,$aid,$type,$content,$cat]) {
    $rows[] = "({$id}, {$pid}, {$aid}, '{$type}', " . q($content) . ", NULL, '{$cat}', '{$cat}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 11. PLAYER METRICS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 11. PLAYER METRICS");
out("-- ============================================================");
out("INSERT INTO player_metrics (id, player_id, coach_id, session_id, date, metrics, evaluation, notes, created_at) VALUES");
$rows = [];
foreach ($metrics as [$id,$pid,$cid,$date,$json,$eval,$notes]) {
    $rows[] = "({$id}, {$pid}, {$cid}, NULL, '{$date}', " . q($json) . ", " . q($eval) . ", " . q($notes) . ", '{$date} 10:00:00')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 12. CONVERSATIONS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 12. CONVERSATIONS");
out("-- ============================================================");
out("INSERT INTO conversations (id, user1_id, user2_id, created_at, last_message_at) VALUES");
$rows = [];
foreach ($convs as [$id,$u1,$u2,$cat,$lma]) {
    $rows[] = "({$id}, {$u1}, {$u2}, '{$cat}', '{$lma}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 13. MESSAGES
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 13. MESSAGES");
out("-- ============================================================");
$chunks = array_chunk($messages, 50);
foreach ($chunks as $chunk) {
    out("INSERT INTO messages (id, conversation_id, sender_id, body, file_path, file_name, file_size, file_mime, read_at, created_at) VALUES");
    $rows = [];
    foreach ($chunk as [$id,$cid,$sid,$body,$cat,$readAt]) {
        $rows[] = "({$id}, {$cid}, {$sid}, " . q($body) . ", NULL, NULL, NULL, NULL, " . ($readAt ? "'{$readAt}'" : 'NULL') . ", '{$cat}')";
    }
    out(implode(",\n", $rows) . ";");
    out();
}

// ---------------------------------------------------------------------------
// 14. NOTIFICATIONS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 14. NOTIFICATIONS");
out("-- ============================================================");
out("INSERT INTO notifications (id, sender_id, type, title, body, file_path, file_name, file_size, created_at) VALUES");
$rows = [];
foreach ($notifs as [$id,$sid,$type,$title,$body,$cat]) {
    $rows[] = "({$id}, {$sid}, '{$type}', " . q($title) . ", " . q($body) . ", NULL, NULL, NULL, '{$cat}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// 15. NOTIFICATION RECIPIENTS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 15. NOTIFICATION RECIPIENTS");
out("-- ============================================================");
$chunks = array_chunk($notifRecipients, 100);
foreach ($chunks as $chunk) {
    out("INSERT INTO notification_recipients (id, notification_id, recipient_id, read_at) VALUES");
    $rows = [];
    foreach ($chunk as [$id,$nid,$rid,$readAt]) {
        $rows[] = "({$id}, {$nid}, {$rid}, " . ($readAt ? "'{$readAt}'" : 'NULL') . ")";
    }
    out(implode(",\n", $rows) . ";");
    out();
}

// ---------------------------------------------------------------------------
// 16. PURCHASE REQUESTS
// ---------------------------------------------------------------------------
out("-- ============================================================");
out("-- 16. PURCHASE REQUESTS");
out("-- ============================================================");
out("INSERT INTO purchase_requests (id, name, description, url, price, category, priority, status, admin_comment, requested_by, reviewed_by, reviewed_at, created_at, updated_at) VALUES");
$rows = [];
foreach ($purchases as [$id,$name,$desc,$url,$price,$cat,$pri,$stat,$comment,$reqBy,$revBy,$revAt,$crAt,$upAt]) {
    $rows[] = "({$id}, " . q($name) . ", " . q($desc) . ", " . q($url) . ", " . qf($price) . ", '{$cat}', '{$pri}', '{$stat}', " . q($comment) . ", {$reqBy}, " . qi($revBy) . ", " . ($revAt ? "'{$revAt}'" : 'NULL') . ", '{$crAt}', '{$upAt}')";
}
out(implode(",\n", $rows) . ";");
out();

// ---------------------------------------------------------------------------
// FOOTER
// ---------------------------------------------------------------------------
out("SET FOREIGN_KEY_CHECKS = 1;");
out();
out("-- ============================================================");
out("-- RESUM");
$sessionCount = $sessionId - 1001;
$cscCount = $cscId - 1001;
$cspCount = $cspId - 1001;
out("-- Locations:             " . count($locations));
out("-- Coaches:               " . count($coaches));
out("-- Staff:                 " . count($staff));
out("-- Players:               " . count($players));
out("-- BonoTypes:             " . count($bonoTypes));
out("-- PlayerBonos:           " . count($playerBonos));
out("-- Classes:               " . count($classDefs));
out("-- ClassSessions:         " . $sessionCount);
out("-- ClassSessionCoaches:   " . $cscCount);
out("-- ClassSessionPlayers:   " . $cspCount);
out("-- PlayerAnnotations:     " . count($annotations));
out("-- PlayerMetrics:         " . count($metrics));
out("-- Conversations:         " . count($convs));
out("-- Messages:              " . count($messages));
out("-- Notifications:         " . count($notifs));
out("-- NotifRecipients:       " . count($notifRecipients));
out("-- PurchaseRequests:      " . count($purchases));
out("-- ============================================================");

// ---------------------------------------------------------------------------
// WRITE FILE
// ---------------------------------------------------------------------------
file_put_contents(OUTPUT_FILE, $out);
$size = round(strlen($out) / 1024);
echo "✓ Generat: " . OUTPUT_FILE . " ({$size} KB)\n";
echo "  Sessions:        {$sessionCount}\n";
echo "  Session coaches: {$cscCount}\n";
echo "  Session players: {$cspCount}\n";
echo "  Total usuaris:   " . (count($coaches) + count($staff) + count($players)) . "\n";
echo "\nPer executar el SQL:\n";
echo "  docker exec -i jp_db mysql -u root -pQwaszx12345_ jp_preparation < " . OUTPUT_FILE . "\n";
