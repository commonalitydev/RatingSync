<?php
/**
 * Imdb class
 */
namespace RatingSync;

require_once "Site.php";
require_once "HttpImdb.php";

/**
 * Communicate to/from the IMDb website
 * - Search for films and tv shows
 * - Get details for each and rate it
 * - Export/Import ratings.
 */
class Imdb extends Site
{
    const IMDB_DATE_FORMAT = "n/j/y";

    public function __construct($username)
    {
        parent::__construct($username);
        $this->http = new HttpImdb($username);
        $this->sourceName = Constants::SOURCE_IMDB;
        $this->dateFormat = self::IMDB_DATE_FORMAT;
        $this->maxCriticScore = 100;
    }
    
    /**
     * Return the rating page's URL within a website. The URL does not
     * include the base URL.  
     *
     * @param array $args See the child class version of args
     *
     * @return string URL of a rating page
     */
    protected function getRatingPageUrl($args)
    {
        if (empty($args) || !is_array($args) || !array_key_exists('pageIndex', $args) || !is_int($args['pageIndex'])) {
            throw new \InvalidArgumentException('$args must be an array with key "pageIndex" and value an int');
        }

        $pageIndex = $args['pageIndex'];
        $startIndex = (($pageIndex - 1) * 100) + 1;
        return '/user/'.urlencode($this->username).'/ratings?start='.$startIndex.'&view=detail&sort=title:asc';
    }

    /**
     * Page number for the next page of ratings. False if not available.
     *
     * @param string $page Html of the current ratings page
     *
     * @return int|false
     */
    protected function getNextRatingPageNumber($page)
    {
        if (0 == preg_match('@Page (\d+) of (\d+)@', $page, $matches)) {
            return false;
        }
        $currentPageNumber = $matches[1];
        $totalPages = $matches[2];

        if ($currentPageNumber == $totalPages) {
            return false;
        }

        return $currentPageNumber + 1;
    }

    /**
     * Create Film objects from the HTML of a ratings page.  Films get only
       data available from ratings page. If the $details param is true, then
       each film goes to another page for full detail. Using $details=true
       can take a long time.
     *
     * @param string     $page         HTML from a page of ratings
     * @param bool|false $details      Get all data for each film
     * @param int|0      $refreshCache Use cache for files modified within mins from now. -1 means always use cache. Zero means never use cache.
     *
     * @return array Film class objects
     */
    protected function getFilmsFromRatingsPage($page, $details = false, $refreshCache = 0)
    {
        $films = array();
        $filmSections = explode('data-const=', $page);
        array_shift($filmSections);
        foreach ($filmSections as $filmSection) {
            // Title
            preg_match('@<b>.*>(.*)<\/a>@', $filmSection, $matches);
            $title = html_entity_decode($matches[1], ENT_QUOTES, "utf-8");

            // Year, Content Type
            preg_match('@<span class=\"year_type\">\((\d\d\d\d) ?([^)]*)\)@', $filmSection, $matches);
            $year = $matches[1];
            $contentType = $matches[2];

            // Film ID
            preg_match('@\"(tt[\d]+)\"@', $filmSection, $matches);
            $filmId = $matches[1];
            
            // Your Score, User Score
            if (1 === preg_match('@id=\"'.$filmId.'\|your\|(\d\d?)\|(\d\d?\.?\d?)@', $filmSection, $matches)) {
                $yourScore = $matches[1];
                $userScore = $matches[2];
            } else {
                $yourScore = null;
                $pregRet = preg_match('@id=\"'.$filmId.'\|imdb\|\d\d?\.?\d?\|(\d\d?\.?\d?)@', $filmSection, $matches);
                $userScore = $matches[1];
            }

            // Image
            $image = null;
            if (0 < preg_match('@<img [^>]*src="(http://.*imdb\.com/images/[A-Z]/[^\"]*)"@', $filmSection, $matches)) {
                $image = $matches[1];
            }

            $films[] = $film = new Film($this->http);
            $film->setTitle($title);
            $film->setYear($year);
            if ($contentType == 'TV Series') {
                $film->setContentType(Film::CONTENT_TV);
            } elseif ($contentType == 'Short Film') {
                $film->setContentType(Film::CONTENT_SHORTFILM);
            } elseif (empty($contentType) || $contentType == 'Documentary') {
                $film->setContentType(Film::CONTENT_FILM);
            }

            $rating = new Rating($this->sourceName);
            $rating->setYourScore($yourScore);
            $rating->setUserScore($userScore);
            //FIXME $rating->setYourRatingDate(\DateTime::createFromFormat($this->dateFormat, $ratingDate));

            $film->setRating($rating, $this->sourceName);
            $film->setFilmId($filmId, $this->sourceName);
            //FIXME $film->setUrlName($urlName, $this->sourceName);
            $film->setImage($image);
            $film->setImage($image, $this->sourceName);

            if ($details) {
                $this->getFilmDetailFromWebsite($film, false, $refreshCache);
            }
        }

        return $films;
    }

    /**
     * Return the film detail page's URL within a website. The URL does not
     * include the base URL.  
     *
     * @param \RatingSync\Film $film Film the URL goes to
     *
     * @return string URL of a film detail page
     */
    protected function getFilmDetailPageUrl($film)
    {
        if (! $film instanceof Film ) {
            throw new \InvalidArgumentException('Function getFilmDetailPageUrl must be given a Film object');
        } elseif ( is_null($film->getFilmId($this->sourceName)) ) {
            throw new \InvalidArgumentException('Function getFilmDetailPageUrl must have Film ID');
        }

        return '/title/'.$film->getFilmId($this->sourceName).'/';
    }

    /**
     * Get the genres from html of the film's detail page. Set the value
     * in the Film param.
     *
     * @param string $page      HTML of the film detail page
     * @param Film   $film      Set the image link in this Film object
     * @param bool   $overwrite Only overwrite data if 1) $overwrite=true OR/AND 2) data is null
     *
     * @return bool true is value is written to the Film object
     */
    protected function parseDetailPageForGenres($page, $film, $overwrite)
    {
        if (!$overwrite && !empty($film->getGenres())) {
            return false;
        }
        $originalGenres = $film->getGenres();
        $didFindGenres = false;
        
        if ($overwrite || empty($film->getGenres())) {
            $film->removeAllGenres();
            
            preg_match_all('/<span class="itemprop" itemprop="genre">([^<]*)<\/span>/', $page, $genreMatches);
            $genres = $genreMatches[1];
            foreach ($genres as $genre) {
                $film->addGenre(html_entity_decode($genre, ENT_QUOTES, "utf-8"));
                $didFindGenres = true;
            }
        }

        if (!$didFindGenres) {
            if (!empty($originalGenres)) {
                foreach ($originalGenres as $genre) {
                    $film->addGenre($genre);
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Get the directors from html of the film's detail page. Set the value
     * in the Film param.
     *
     * @param string $page      HTML of the film detail page
     * @param Film   $film      Set the image link in this Film object
     * @param bool   $overwrite Only overwrite data if 1) $overwrite=true OR/AND 2) data is null
     *
     * @return bool true is value is written to the Film object
     */
    protected function parseDetailPageForDirectors($page, $film, $overwrite)
    {
        if (!$overwrite && !empty($film->getDirectors())) {
            return false;
        }
        $originalDirectors = $film->getDirectors();
        $didFindDirectors = false;
        
        if ($overwrite || empty($film->getDirectors())) {
            $film->removeAllDirectors();
            if (0 < preg_match('/<h4 class="inline">Director[s]?:<\/h4>(.*?)Writer/s', $page, $sectionMatches)) {
                preg_match_all('/itemprop="name">([^<]*)</', $sectionMatches[1], $directorMatches);
                $directors = $directorMatches[1];
                foreach ($directors as $director) {
                    $film->addDirector(html_entity_decode($director, ENT_QUOTES, "utf-8"));
                    $didFindDirectors = true;
                }
            }
        }

        if (!$didFindDirectors) {
            if (!empty($originalDirectors)) {
                foreach ($originalDirectors as $director) {
                    $film->addDirector($director);
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Regular expression to find the film title in film detail HTML page
     *
     * @return string Regular expression to find the film title in film detail HTML page
     */
    protected function getDetailPageRegexForTitle() {
        return '/<title>(.*) \(.*\d\d\d\d[^\)]*\) - IMDb<\/title>/';
    }

    /**
     * Regular expression to find the film year in film detail HTML page
     *
     * @return string Regular expression to find the film year in film detail HTML page
     */
    protected function getDetailPageRegexForYear() {
        return '/<title>.* \([^\d]*(\d\d\d\d)[^\)]*\) - IMDb<\/title>/';
    }

    /**
     * Regular expression to find the image in film detail HTML page
     *
     * @return string Regular expression to find the image in film detail HTML page
     */
    protected function getDetailPageRegexForImage() {
        return '/title="[^\(]* \(\d\d\d\d\) Poster"\nsrc="([^"]+)"/';
    }

    /**
     * Regular expression to find Content Type in film detail HTML page
     *
     * @return string Regular expression to find Content Type in film detail HTML page
     */
    protected function getDetailPageRegexForContentType() {
        return '/<div class="infobar">\s*([a-zA-Z \-\/]+)\s*&nbsp;<</';
    }

    /**
     * Regular expression to find Film Id in film detail HTML page
     *
     * @param \RatingSync\Film $film Film data
     *
     * @return string Regular expression to find Film Id in film detail HTML page
     */
    protected function getDetailPageRegexForFilmId($film) {
        return '/<meta property="og:url" content=".*\/(.+)\/"/';
    }

    /**
     * Regular expression to find URL Name in film detail HTML page
     *
     * @return string Regular expression to find URL Name in film detail HTML page
     */
    protected function getDetailPageRegexForUrlName() {
        return '';
    }

    /**
     * Regular expression to find your rating score in film detail HTML page
     *
     * @param \RatingSync\Film $film Film data
     *
     * @return string Regular expression to find your rating score in film detail HTML page
     */
    protected function getDetailPageRegexForYourScore($film) {
        return '/<span class="rating-rating rating-your"><span class="value">(\d\d?)<\/span>/';
    }

    /**
     * Regular expression to find your rating date in film detail HTML page
     *
     * @return string Regular expression to find your rating date in film detail HTML page
     */
    protected function getDetailPageRegexForRatingDate() {
        return '';
    }

    /**
     * Regular expression to find suggested score in film detail HTML page
     *
     * @param \RatingSync\Film $film Film data
     *
     * @return string Regular expression to find suggested score in film detail HTML page
     */
    protected function getDetailPageRegexForSuggestedScore($film) {
        return '';
    }

    /**
     * Regular expression to find critic score in film detail HTML page
     *
     * @return string Regular expression to find critic score in film detail HTML page
     */
    protected function getDetailPageRegexForCriticScore() {
        return '/provided by Metacritic\.com" > (\d\d?)\/100/';
    }

    /**
     * Regular expression to find user score in film detail HTML page
     *
     * @return string Regular expression to find user score in film detail HTML page
     */
    protected function getDetailPageRegexForUserScore() {
        return '/<span itemprop="ratingValue">(\d\.?\d?)<\/span>/';
    }

    /**
     * Get the content type from html of the film's detail page. Set the value
     * in the Film param.
     *
     * @param string $page      HTML of the film detail page
     * @param Film   $film      Set the image link in this Film object
     * @param bool   $overwrite Only overwrite data if 1) $overwrite=true OR/AND 2) data is null
     *
     * @return bool true is value is written to the Film object
     */
    protected function parseDetailPageForContentType($page, $film, $overwrite)
    {
        $success = parent::parseDetailPageForContentType($page, $film, $overwrite);

        if (empty($film->getContentType())) {
            $film->setContentType(Film::CONTENT_FILM);
            $success = true;
        }
        
        return $success;
    }
}