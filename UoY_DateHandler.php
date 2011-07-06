<?php
require_once 'UoY_Date.php';

date_default_timezone_set('Europe/London');

/**
 * Class for handling University of York term dates.
 * 
 * @author Gareth Andrew Lloyd <gareth@ignition-web.co.uk>
 * @author Matt Windsor <mattwindsor@btinternet.com>
 */
class UoY_DateHandler
{   
    // Term identifiers
    /** Autumn term. */
    const TERM_AUTUMN = 1;
    /** Spring term. */
    const TERM_SPRING = 2;
    /** Summer term. */
    const TERM_SUMMER = 3;

    /** 
     * Lowest valid term identifier.  
     * 
     * Any number below this is not a valid term identifier.
     */
    const TERM_LOWER_BOUND = self::TERM_AUTUMN;
  
    /**
     * Highest valid term identifier.
     * 
     * Any number above this is not a valid term identifier.
     */
    const TERM_UPPER_BOUND = self::TERM_SUMMER;
  
    // Break identifiers
    /** Winter break. */
    const BREAK_WINTER = 1;
    /** Spring break. */
    const BREAK_SPRING = 2;
    /** Summer break. */
    const BREAK_SUMMER = 3;
    
    /** 
     * Lowest valid break identifier.  
     * 
     * Any number below this is not a valid break identifier.
     */
    const BREAK_LOWER_BOUND = self::BREAK_WINTER;
  
    /**
     * Highest valid term identifier.
     * 
     * Any number above this is not a valid break identifier.
     */
    const BREAK_UPPER_BOUND = self::BREAK_SUMMER;

    // Day identifiers
    /** Monday. */
    const DAY_MONDAY = 1;
    /** Tuesday. */
    const DAY_TUESDAY = 2;
    /** Wednesday. */
    const DAY_WEDNESDAY = 3;
    /** Thursday. */
    const DAY_THURSDAY = 4;
    /** Friday. */
    const DAY_FRIDAY = 5;
    /** Saturday. */
    const DAY_SATURDAY = 6;
    /** Sunday. */
    const DAY_SUNDAY = 7;
    
    /** 
     * Lowest valid day identifier.  
     * 
     * Any number below this is not a valid day identifier.
     */
    const DAY_LOWER_BOUND = self::DAY_MONDAY;
  
    /**
     * Highest valid day identifier.
     * 
     * Any number above this is not a valid day identifier.
     */
    const DAY_UPPER_BOUND = self::DAY_SUNDAY;
    
    //variables to change
    protected static $_file = 'uoy-term-dates.xml';
    protected static $_url = 'localhost';
    protected static $_localdir = 'xml';
    protected static $_bootloader = 'http://ury.york.ac.uk/xml/uoy-term-dates.xml';

    public function __construct()
    {
      if (!defined('STDIN')){
        self::$_localdir = $_SERVER['DOCUMENT_ROOT'].'/xml';
      }
    }

    protected static function bootload_file()
    {
        $dest = self::$_localdir . '/' . self::$_file;
        if (!file_exists(self::$_localdir)) {
          $res = mkdir(self::$_localdir,0770,true);
          if (!$res){
            return false;
          }
        }
        $res = touch($dest, 0);
        if (!$res) {
            return false; //local file doesn't exist
        }
        return copy(self::$_bootloader, $dest);
    }

    protected static function get_years($xml)
    {
        $years = $xml->xpath('/uoytermdates/termdates/year');
        $res = array();
        foreach ($years as $y) {
            $res[] = (integer) $y;
        }
        sort($res, SORT_NUMERIC);
        return $res;
    }

    protected static function get_updated_time($xml)
    {
        $res = $xml->xpath('/uoytermdates/updated[1]');
        return @strtotime($res[0]);
    }

    protected static function add_year_to_cache($xml, $year, $tmpxml)
    {
        //TODO add validation
        //MAYBE rename to copy_year_data
        $res = $xml->xpath("/uoytermdates/termdates[year=$year]");
        $data = dom_import_simplexml($res[0]);
        $dom = dom_import_simplexml($tmpxml);
        $dom = $dom->ownerDocument;
        $node = $dom->importNode($data, true);
        $dom->documentElement->appendChild($node);
        $tmpxml = simplexml_import_dom($dom);
    }

    protected static function get_trusted_sources($tmpxml)
    {
        $res = $tmpxml->xpath('/uoytermdates/source[trusted="yes"]/url');
        $result = array();
        foreach ($res as $r) {
            $result[] = $r;
        }
        return $result;
    }

    protected static function get_sources($xml)
    {
        $res = $xml->xpath('/uoytermdates/source/url');
        $result = array();
        foreach ($res as $r) {
            $result[] = $r;
        }
        return $result;
    }

    protected static function change_update_time_of_cache($time, $tmpxml)
    {
        //MAYBE rename to set_updated_time
        $tmpxml->updated[0] = @date('Y-m-d\TH:i:sP', $time);
    }

    protected static function write_to_cache($tmpxml)
    {
        //MAYBE rename to write_cache
        return file_put_contents(self::$_localdir . '/' . self::$_file, $tmpxml->asXML());
    }

    protected static function add_source_to_cache($trust, $tmpxml)
    {
        //TODO
    }

    protected static function cache_exists()
    {
        $file = self::$_file;
        $localdir = self::$_localdir;
        if (!file_exists("$localdir/$file")) {
            return self::bootload_file();
        }
        return true;
    }

    public static function update_cache()
    {
        $url = self::$_url;
        $file = self::$_file;
        $localdir = self::$_localdir;
        
        if (!self::cache_exists()) {
          return false; //cache file missing and can't be made
        }

        $tmpxml = simplexml_load_file("$localdir/$file");

        $sources = self::get_trusted_sources($tmpxml);
        $lastupdate = self::get_updated_time($tmpxml);
        $localyears = self::get_years($tmpxml);

        $sourceslist = $sources;
        $updated = false;
        foreach ($sources as $f) {
            if ($f != "http://$url/$file") {
                $xml = @simplexml_load_file($f);
                if (!$xml)
                    break; //remote file doesn't exist
                $utime = self::get_updated_time($xml);
                //find newer version
                if ($lastupdate < $utime) {
                    //update sources
                    $sourcesremote = self::get_sources($xml);
                    $sourcestoupdate = array_diff($sourcesremote, $sourceslist);
                    foreach ($sourcestoupdate as $s) {
                        self::add_source_to_cache($s, false, $tmpxml);
                        $sourcelist[] = $s;
                    }
                    //update termdates
                    $yearremote = self::get_years($xml);
                    $yearstoupdate = array_diff($yearremote, $localyears);
                    foreach ($yearstoupdate as $year) {
                        self::add_year_to_cache($xml, $year, $tmpxml);
                        $yearlocal[] = $year;
                    }
                    //update timestamp
                    if (count($yearstoupdate) != 0) {
                        self::change_update_time_of_cache($utime, $tmpxml);
                        $lastupdate = $utime;
                        $updated = true;
                    }
                }
            }
        }
        if ($updated) {
            return self::write_to_cache($tmpxml);
        }
        return true;
    }

    public static function year_exists($year, $update = false)
    {
        if (!self::cache_exists()) {
          return false; //cache file missing and can't be made
        }
        $ld = self::$_localdir;
        $file = self::$_file;
        $tmpxml = simplexml_load_file("$ld/$file");
        $res = $tmpxml->xpath("/uoytermdates/termdates[year=$year]");
        if (($res == array()) && $update) {
            self::update_cache();
            $res = $tmpxml->xpath("/uoytermdates/termdates[year=$year]");
        }
        return $res != array(); //no year exist in xml even after update
    }

    //assumption 01-Sept is the earliest academic year start

    public static function year_number($date)
    {
        //MAYBE rename to year_number
        return @date("Y", $date - @strtotime("1st September 1970"));
    }

    public static function term_info($date)
    {
        $ld = self::$_localdir;
        $file = self::$_file;
        $year = self::year_number($date);
        if (!self::year_exists($year, true)) {
            return false;
        }
        $tmpxml = simplexml_load_file("$ld/$file");
        $res = $tmpxml->xpath("/uoytermdates/termdates[year=$year]");
        $feature[] = @strtotime("31st August $year");
        $feature[] = @strtotime("1st September " . ($year + 1) . "");
        foreach ($res[0]->term as $t) {
            $feature[] = @strtotime($t->start);
            $feature[] = @strtotime($t->end);
        }
        sort($feature, SORT_NUMERIC);
        $term = 0;
        for ($i = 0; $i < count($feature) - 1; $i = $i + 1) {
            if ($i % 2 == 0) {
                //Break (exclusive dates)
                if (($date > $feature[$i] + 60 * 60 * 24) && ($date < $feature[$i + 1])) {
                    $term = $i;
                    break;
                }
            } else {
                //Term (inclusive dates)
                if (($date >= $feature[$i]) && ($date <= $feature[$i + 1] + 60 * 60 * 24)) {
                    $term = $i;
                    break;
                }
            }
        }
        //0 - $year-1 summer break
        //1 - term 1
        //2 - $year christmas break
        //3 - term 2
        //4 - $year easter break
        //5 - term 3
        //6 - $year summer break
        if ($term != 0) {
            $weekdayoffset = @strtotime("last Monday", $feature[$term]);
            $relativetoterm = $date - $weekdayoffset;
            $relativetoterm /= 60 * 60 * 24 * 7;
            $week = (int) $relativetoterm;
        } else {
            $weekdayoffset = @strtotime("last Monday 31st August ".($year - 1));
            $term_details = self::term_info($weekdayoffset);
            if (!$term_details) {
              return false; //can't infer any information for the week number
            }
            $relativetoterm = $date - $weekdayoffset;
            $relativetoterm /= 60 * 60 * 24 * 7;
            $week = (int) $relativetoterm + $term_details['weeknum'] - 1;
        }
        $result['weeknum'] = $week;
        $result['termnum'] = (($term % 2) == 1) ? ($term + 1) / 2 : 0;
        $result['breaknum'] = (($term % 2) == 0) ? ($term) / 2 : 0;
        if ($term == 0)
            $result['breaknum'] = 3;
        $result['yearnum'] = ($term != 0) ? $year : $year - 1;
        switch ($term) {
            case 0: case 6: $result['termname'] = 'Summer Break';
                break;
            case 1: $result['termname'] = 'Autumn Term';
                break;
            case 2: $result['termname'] = 'Winter Break';
                break;
            case 3: $result['termname'] = 'Spring Term';
                break;
            case 4: $result['termname'] = 'Spring Break';
                break;
            case 5: $result['termname'] = 'Summer Term';
                break;
        }
        $result['yearname'] = $result['yearnum'] . '-' . ($result['yearnum'] + 1);

        return new UoY_Date(
            $result['yearnum'],
            $result['termnum'] === 0 ? $result['breaknum'] : $result['termnum'],
            ($result['termnum'] === 0), // Whether or not this is a break
            $result['weeknum'],
            intval(date('N', $date)) // Day
        );
    }

    public static function test()
    {
        for ($i = 0; $i < 365; $i++) {
            $day = @strtotime("1st September 2010") + $i * 60 * 60 * 24;
            echo @date("Y-m-d", $day) . "\n";
            if (self::term_info($day) === false) {
                echo "not convertable using given data.\n";
            } else {
                echo self::term_info($day)->toString() . "\n";
            }
        }
    }

}

?>