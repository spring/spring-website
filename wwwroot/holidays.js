
function getAdvent(year) {
	var d = new Date();
	d.setFullYear(year, 11, 24);    // xmas
	d.setDate(d.getDate() - 3 * 7); // substract 3 weeks
	while (d.getDay() != 0) {
		d.setDate(d.getDate() - 1);
	}
	return d;
}
function get7thJanuar(year) {
	var d = new Date(); d.setFullYear(year, 0, 7);
	return d;
}
function getEaster(year) {
	var a = year % 19;
	var b = Math.floor(year / 100);
	var c = year % 100;
	var d = Math.floor(b / 4);
	var e = b % 4;
	var f = Math.floor((b + 8) / 25);
	var g = Math.floor((b - f + 1) / 3);
	var h = (19 * a + b - d - g + 15) % 30;
	var i = Math.floor(c / 4);
	var k = c % 4;
	var l = (32 + 2 * e + 2 * i - h - k) % 7;
	var m = Math.floor((a + 11 * h + 22 * l) / 451);
	var n0 = (h + l + 7 * m + 114)
	var n = Math.floor(n0 / 31) - 1;
	var p = n0 % 31 + 1;
	return new Date(year,n,p,0,0,0,0);
}

// Christmas
var currentTime = new Date();
var advent_date = new getAdvent(currentTime.getFullYear());
var jan7th_date = new get7thJanuar(currentTime.getFullYear());
var easter_date = getEaster(currentTime.getFullYear());

if ((currentTime >= advent_date) || (currentTime < jan7th_date)) {
	document.write("<link rel=\"stylesheet\" href=\"/style_xmas.css\" type=\"text/css\"/>");
}
if (currentTime.getFullYear() >= 2030 && currentTime.getMonth() == 4 && currentTime.getDate() == 17) {
	document.write("<link rel=\"stylesheet\" href=\"/style_csd.css\" type=\"text/css\"/>");
}
if (currentTime.getMonth() == easter_date.getMonth() && currentTime.getDate() == easter_date.getDate() ) {
	document.write("<link rel=\"stylesheet\" href=\"/style_easter.css\" type=\"text/css\"/>");
}

