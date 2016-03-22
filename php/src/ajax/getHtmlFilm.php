<?php
namespace RatingSync;

require_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "main.php";
require_once "getHtmlRating.php";

$username = getUsername();

function getHtmlFilm($film, $titleNum = null, $withImage = true) {
    if (empty($film)) {
        return "";
    }

    $title = $film->getTitle();
    $year = $film->getYear();
    $uniqueName = $film->getUniqueName(Constants::SOURCE_RATINGSYNC);
    $imdbLabel = "IMDb";
    $imdbScore = $film->getUserScore(Constants::SOURCE_IMDB);
    $imdb = new Imdb(getUsername());
    $imdbFilmUrl = $imdb->getFilmUrl($film);
    $yourRatingDate = $film->getRating(Constants::SOURCE_RATINGSYNC)->getYourRatingDate();
    $dateStr = null;
    if (!empty($yourRatingDate)) {
        $date = date_format($yourRatingDate, 'n/j/Y');
        $dateStr = "You rated this $date";
    }
    $titleNumStr = "";
    if (!empty($titleNum)) {
        $titleNumStr = "$titleNum. ";
    }
    
    $response = "";
    if ($withImage) {
        $image = Constants::RS_HOST . $film->getImage(Constants::SOURCE_RATINGSYNC);
        $response .= "<poster><img src='$image' width='150px'/></poster>\n";
    }

    $response .= "<detail>";
    $response .= "  <div class='film-line'>$titleNumStr<span class='film-title'>$title</span> ($year)</div>\n";
    $response .= "  <div align='left'>\n";
    $response .=      getHtmlRatingStars($film, $titleNum, $withImage);
    $response .= "  </div>\n";
    $response .= "  <div class='rating-date'>$dateStr</div>\n";
    $response .= "  <div><a href='$imdbFilmUrl' target='_blank'>$imdbLabel:</a> $imdbScore</div>\n";
    $response .= "</detail>";
    
    return $response;
}

?>