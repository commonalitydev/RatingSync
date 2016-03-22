
document.addEventListener('DOMContentLoaded', function () {
    chrome.tabs.executeScript(null, {file: "getSearchTerms.js"}, function() { });
});

function renderStatus(statusText) {
  document.getElementById('status').textContent = statusText;
}

function notFound(source)
{
    var msg = "<div>Unable to find title on this " + source + " page</div>";
    document.getElementById("searchResult").innerHTML = msg;
}

function notSupported(source)
{
    var msg = "<div>Here are the sites currently supported<ul><li>IMDb</li><li>Rotten Tomatoes</li><li>Netflix</li></ul></div>";
    document.getElementById("searchResult").innerHTML = msg;
}

function searchFilm(searchTerms)
{
    renderStatus('Searching...');
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function () {
	    if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            var searchResultElement = document.getElementById("searchResult");
	        searchResultElement.innerHTML = xmlhttp.responseText;
            addStarListeners(searchResultElement);
    		renderStatus('');
	    } else if (xmlhttp.readyState == 4) {
	        renderStatus('Not found ' + searchTerm + ' at ' + source);
	    }
	}

    var params = "?i=1";
    if (searchTerms.uniqueName != "undefined") { params = params + "&q=" + searchTerms.uniqueName; }
    if (searchTerms.source != "undefined") { params = params + "&source=" + searchTerms.source; }
    if (searchTerms.title != "undefined") { params = params + "&t=" + searchTerms.title; }
    if (searchTerms.year != "undefined") { params = params + "&y=" + searchTerms.year; }
    if (searchTerms.contentType != "undefined") { params = params + "&ct=" + searchTerms.contentType; }
	xmlhttp.open("GET", "http://192.168.1.105:55887/php/src/ajax/getSearchFilm.php" + params, true);
	xmlhttp.send();
}

function addStarListeners(el) {
    var stars = el.getElementsByClassName("rating-star");
    for (i = 0; i < stars.length; i++) {
        addStarListener(stars[i].getAttribute("id"));
    }
}

function addStarListener(elementId) {    
	var star = document.getElementById(elementId);
	if (star != null) {
		var uniqueName = star.getAttribute('data-uniquename');
		var score = star.getAttribute('data-score');
		var titleNum = star.getAttribute('data-title-num');
		var withImage = star.getAttribute('data-image');

		var mouseoverHandler = function () { showYourScore(uniqueName, score, 'new'); };
		var mouseoutHandler = function () { showYourScore(uniqueName, score, 'original'); };
		var clickHandler = function () { rateFilm(uniqueName, score, titleNum, withImage); };

        star.addEventListener("mouseover", mouseoverHandler);
        star.addEventListener("mouseout", mouseoutHandler);
        star.addEventListener("click", clickHandler);
	}
}

function rateFilm(uniqueName, score, titleNum, withImage) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function () {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            var searchResultElement = document.getElementById("searchResult");
	        searchResultElement.innerHTML = xmlhttp.responseText;
            addStarListeners(searchResultElement);
    		renderStatus('Rating Saved');
        }
    }
    xmlhttp.open("GET", "http://192.168.1.105:55887/php/src/ajax/setRating.php?un=" + uniqueName + "&s=" + score + "&i=" + withImage, true);
    xmlhttp.send();
    renderStatus('Saving...');
}

function showYourScore(uniqueName, hoverScore, mousemove) {
    var score = hoverScore;
    if (mousemove == "original") {
        score = document.getElementById("original-score-" + uniqueName).getAttribute("data-score");
    }

    if (score == "10") {
        score = "01";
    }
    document.getElementById("your-score-" + uniqueName).innerHTML = score;
}

chrome.runtime.onMessage.addListener(function (request, sender) {
    if (request.action == "setSearchTerms") {
        searchFilm(request.search);
    }
});