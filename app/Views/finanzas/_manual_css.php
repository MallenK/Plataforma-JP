<?php /** Estilos compartidos del manual de Finanzas y de la ayuda de Revisión. */ ?>
<style>
.fin-manual { color:#1e293b; font-size:15px; line-height:1.65; max-width:900px; }
.fin-manual h2 { font-size:24px; font-weight:800; color:#0f172a; margin:36px 0 10px; padding-top:8px; border-top:2px solid #e2e8f0; }
.fin-manual h2:first-of-type { border-top:0; margin-top:8px; }
.fin-manual h3 { font-size:18px; font-weight:700; color:#0f172a; margin:24px 0 8px; }
.fin-manual p { margin:0 0 12px; }
.fin-manual ul, .fin-manual ol { margin:0 0 14px; padding-left:22px; }
.fin-manual li { margin-bottom:6px; }
.fin-manual .fm-box { border-radius:10px; padding:14px 16px; margin:14px 0; font-size:14.5px; }
.fin-manual .fm-tip { background:#eff6ff; border:1px solid #bfdbfe; }
.fin-manual .fm-warn { background:#fffbeb; border:1px solid #fde68a; }
.fin-manual .fm-ok { background:#ecfdf5; border:1px solid #a7f3d0; }
.fin-manual .fm-box strong:first-child { display:block; margin-bottom:4px; color:#0f172a; }
.fin-manual table { width:100%; border-collapse:collapse; margin:10px 0 16px; font-size:14px; }
.fin-manual th, .fin-manual td { border:1px solid #e2e8f0; padding:8px 10px; text-align:left; vertical-align:top; }
.fin-manual th { background:#f1f5f9; font-weight:700; color:#0f172a; }
.fin-manual .fm-shot { margin:14px 0 20px; }
.fin-manual .fm-shot img { width:100%; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 2px 8px rgba(15,23,42,.08); }
.fin-manual .fm-shot figcaption { font-size:12.5px; color:#64748b; margin-top:6px; text-align:center; }
.fin-manual .fm-steps { counter-reset:step; list-style:none; padding-left:0; }
.fin-manual .fm-steps > li { counter-increment:step; position:relative; padding-left:38px; margin-bottom:10px; }
.fin-manual .fm-steps > li::before { content:counter(step); position:absolute; left:0; top:1px; width:26px; height:26px; border-radius:50%;
    background:#1d4ed8; color:#fff; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; }
.fin-manual .fm-toc { columns:2; column-gap:32px; font-size:14px; }
.fin-manual .fm-toc a { color:#1d4ed8; text-decoration:none; }
.fin-manual kbd, .fin-manual .fm-btn { display:inline-block; font-family:inherit; font-size:13px; font-weight:700; padding:1px 8px; border-radius:6px; background:#f1f5f9; border:1px solid #cbd5e1; color:#0f172a; white-space:nowrap; }
.fin-manual .fm-q { font-weight:700; color:#0f172a; margin:16px 0 4px; }
@media (max-width: 640px) { .fin-manual .fm-toc { columns:1; } .fin-manual { font-size:14.5px; } }
</style>
