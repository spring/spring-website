
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

// workaround: chrome on windows has a font render bug that makes AA fail (2014)
// see http://stackoverflow.com/questions/11487427/is-there-any-font-smoothing-in-google-chrome
// remove when Chrome v35 is out
if ((window.chrome) && (navigator.appVersion.indexOf("Win") != -1)) {
	var forceAntiAliasing = function(){/*
<style>
@media screen and (-webkit-min-device-pixel-ratio:0) {
	@font-face {
		font-family: 'FreeSans Spring';
		font-weight: 400;
		src: local(Arial);
	}

	@font-face {
		font-family: 'FreeSans Spring';
		font-weight: bold;
		src: local(Arial);
	}
}
</style>
*/}.toString().slice(14,-3);

	document.write(forceAntiAliasing);
}
