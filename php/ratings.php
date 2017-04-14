<?php
namespace RatingSync;

require_once "main.php";
require_once "pageHeader.php";
require_once "src/SessionUtility.php";
require_once "src/Film.php";
require_once "src/Filmlist.php";

require_once "src/ajax/getHtmlFilmlists.php";

$username = getUsername();
$listnames = Filmlist::getUserListnamesFromDbByParent($username);
$pageHeader = getPageHeader(true, $listnames);
$pageFooter = getPageFooter();
$filmlistHeader = "";
$pageNum = array_value_by_key("p", $_GET);
if (empty($pageNum)) {
    $pageNum = 1;
}

if (!empty($username)) {
    $filmlistHeader = getHtmlFilmlistsHeader($listnames, null, "Your Ratings");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSync: Your Ratings</title>
    <link href="../css/bootstrap_rs.min.css" rel="stylesheet">
    <link href="../css/rs.css" rel="stylesheet">
    <?php if (empty($username)) { echo '<script type="text/javascript">window.location.href = "/php/Login"</script>'; } ?>
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="../js/bootstrap_rs.min.js"></script>
    <script src="../Chrome/constants.js"></script>
    <script src="../Chrome/rsCommon.js"></script>
    <script src="../js/ratings.js"></script>
    <script src="../js/filmlistHeader.js"></script>
    <script src="../js/film.js"></script>
</head>

<body onclick="hideFilmDetail()">

<div class="container">
  <?php echo $pageHeader; ?>
  <?php echo $filmlistHeader; ?>

  <div id='rating-detail' class='rating-detail' onMouseEnter="hideable = false;" onMouseLeave="hideable = true;"></div>

    <div id="film-table"></div>

  <ul id="pagination" class="pager" hidden>
    <li id="previous"><a href="./ratings.php">Previous</a></li>
    <li id="next"><a href="./ratings.php">Next</a></li>
  </ul>
    
  <?php echo $pageFooter; ?>
</div>

<script>
var contextData;
var currentPageNum = 1;
var defaultPageSize = 100;
checkFilterFromUrl();
getRsRatings(defaultPageSize, <?php echo $pageNum; ?>);
</script>
          
</body>
</html>
