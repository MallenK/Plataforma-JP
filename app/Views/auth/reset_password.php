<div style="height:100vh;display:flex;align-items:center;justify-content:center;
background:linear-gradient(135deg,#0f172a,#1e3a8a);">

<div style="width:380px;padding:40px;border-radius:20px;
background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);
border:1px solid rgba(255,255,255,0.2);color:white;">

<h3 style="text-align:center;margin-bottom:20px;">Nueva contraseña</h3>

<div id="errorBox"></div>

<form id="resetForm">
<input type="hidden" name="token" value="<?= $token ?>">

<input type="password" name="password" placeholder="Nueva contraseña"
style="width:100%;padding:12px;margin-bottom:20px;border-radius:10px;background:rgba(255,255,255,0.1);color:white;">

<button style="width:100%;padding:12px;background:#3b82f6;color:white;border:none;border-radius:10px;">
Guardar
</button>
</form>

</div>
</div>