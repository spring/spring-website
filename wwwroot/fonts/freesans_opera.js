
// workaround: .woff fonts are blurry in Opera use ttf instead
if (window.opera) {
	var overrideWithTTF = function(){/*
<style>
@font-face {
	font-family: 'FreeSans Spring';
}

@font-face {
	font-family: 'FreeSans Spring';
}
</style>
*/}.toString().slice(14,-3);

	document.write(overrideWithTTF);
}

// workaround: chrome on win7 disables antialiasing on webfonts for unknown reason
if ((window.chrome) && (navigator.appVersion.indexOf("Win") != -1)) {
	var forceAntiAliasing = function(){/*
<style>
html {
	text-shadow: 0px 0px 0px rgba(0,0,0,0.01);
}
</style>
*/}.toString().slice(14,-3);

	document.write(forceAntiAliasing);
}
