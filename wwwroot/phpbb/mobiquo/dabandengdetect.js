
function detectDabandeng() {
    if (document.cookie.indexOf("dabandeng_redirect=false") < 0) {
        if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i))) {
            if (confirm("我们的论坛可以通过 iPhone 大板凳应用直接访问，了解更多？")) {
                document.cookie = "dabandeng_redirect=false";
                window.location = "http://www.dabandeng.com/files/wap/index.html";
            } else {
                setDabandengCookies();
            }
        } else if(navigator.userAgent.match(/android/i)) {
            if (confirm("我们的论坛可以通过 android 大板凳应用直接访问，了解更多？")) {
                document.cookie = "dabandeng_redirect=false";
                window.location = "http://www.dabandeng.com/files/wap/index.html";
            } else {
                setDabandengCookies();
            }
//        } else if((navigator.userAgent.match(/Symbian/i)) || (navigator.userAgent.match(/Nokia/i))) {
//            if (confirm("This forum is Nokia Native! Click OK to learn more about Dabandeng for Nokia.")) {
//                document.cookie = "dabandeng_redirect=false";
//                window.location = "http://store.ovi.com/content/22647?clickSource=browse&contentArea=applications";
//            } else {
//                setDabandengCookies();
//            }
        }
    }
}

function setDabandengCookies() {
    var date = new Date();
    var days = 30;
    date.setTime(date.getTime()+(days*24*60*60*1000));
    var expires = "; expires="+ date.toGMTString();
    document.cookie = "dabandeng_redirect=false" + expires;
}

detectDabandeng();
 