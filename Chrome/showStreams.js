
chrome.runtime.onMessage.addListener(onMessage);

function onMessage(request, sender) {
    if (request.action == "showStreams") {
        showStreams();
    } else if (request.action == "forwardToParent" && request.subject == "streamInfoReady") {
        showOneStream(request.streamResponse);
    }
}

function showStreams()
{
    var filmStreamsElements = document.getElementsByClassName("streams");
    for (i = 0; i < filmStreamsElements.length; i++) {
        var streamsEl = filmStreamsElements[i];
        showStreamsForOneFilm(streamsEl);
    }
}

function showStreamsForOneFilm(streamsEl)
{
    var streamElements = streamsEl.getElementsByClassName("stream");
    for (j = 0; j < streamElements.length; j++) {
        var el = streamElements[j];
        var streamDateStr = el.getAttribute("data-stream-date");
        var lastWeek = new Date(Date.now() - (1000*60*60*24*7));
        if (!streamDateStr || streamDateStr == "" || streamDateStr == "null" || new Date(streamDateStr) < lastWeek) {
            var sourceName = el.getAttribute("data-source-name");
            var title = el.getAttribute("data-title");
            var year = el.getAttribute("data-year");
            var contentType = el.getAttribute("data-content-type");
            var uniqueName = el.getAttribute("data-uniquename");
            var uniqueEpisode = el.getAttribute("data-unique-episode");
            var uniqueAlt = el.getAttribute("data-unique-alt");
            
            var infoText = '{';
            infoText = infoText + '"elementId": "' + el.getAttribute("id") + '"';
            infoText = infoText + ',"filmId": "' + el.getAttribute("data-film-id") + '"';
            infoText = infoText + ', "sourceName": "' + sourceName + '"';
            infoText = infoText + ', "title": "' + encodeURI(title) + '"';
            infoText = infoText + ', "year": "' + year + '"';
            infoText = infoText + ', "contentType": "' + contentType + '"';
            infoText = infoText + ', "uniqueName": "' + uniqueName + '"';
            infoText = infoText + ', "uniqueEpisode": "' + uniqueEpisode + '"';
            infoText = infoText + ', "uniqueAlt": "' + uniqueAlt + '"';
            infoText = infoText + '}';
            var streamInfo = JSON.parse(infoText);
            
            var providerUrl = "";
            var isSearch = false;
            if (sourceName == "Netflix") {
                providerUrl = "https://www.netflix.com";
                if (uniqueName) {
                    providerUrl = providerUrl + "/title/" + uniqueName;
                }
                else {
                    isSearch = true;
                    providerUrl = providerUrl + "/search/" + encodeURI(title);
                }

                chrome.runtime.sendMessage({action: "createProviderTab", url: providerUrl, streamInfo: streamInfo, isSearch: isSearch});
            }
        }
    }
}

function showOneStream(streamResponse) {
    if (!streamResponse) {
        return;
    }

    var streamInfo = streamResponse.streamInfo;
    var streamUrl = streamResponse.streamUrl;
    var el = document.getElementById(streamInfo.elementId);
    var html = "";
    if (streamUrl && streamUrl != "undefined" && streamUrl != "NONE") {
        html = html + "<a href='" + streamUrl + "' target='_blank'>\n";
        html = html + "    <div class='stream-icon icon-" + streamInfo.sourceName + "' title='Watch on " + streamInfo.sourceName + "'></div></a>\n";
        html = html + "</a>\n";
    }
    el.innerHTML = html;

    var nowDate = new Date();
    var nowStr = nowDate.toISOString();
    el.setAttribute("data-stream-date", nowStr.substr(0, 10));
}