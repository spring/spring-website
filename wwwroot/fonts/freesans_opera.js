
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
