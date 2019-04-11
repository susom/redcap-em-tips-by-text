<?php

namespace Stanford\TipsByText;
/** @var \Stanford\TipsByText\TipsByText $module */


class SmsMessages
{

    public $tipsArray;    // tips by day
    private $original;      //original input

    //private $filename = 'literacy_texts_030119_utf8.csv';
    private $filename = 'TEST_literacy_texts_030119_utf8.csv'; //send DAILY TEXTS to TEST

    public function __construct()
    {
        global $module;

        //$this->tipsArray = self::loadTipsFile();
        $this->tipsArray = $this->loadEdocsFile();

        if (empty($this->tipsArray)) {
            return false;
        }

        //print "<pre>" . print_r($this->tipsArray,true). "</pre>";
    }


    function loadEdocsFile() {
        global $module;

        $edoc_id = $module->getProjectSetting('sms-input-file');

        if (is_numeric($edoc_id)) {
            $path = \Files::copyEdocToTemp($edoc_id);
        } else {
            $module->emError("Unable to find a valid json source file for $edoc_id. ");
            return false;
        }

        if ($path) {
            //$tips =  $this->loadFile($path);
            $tips =  $this->loadTipsFile($path);

            //unset the temp dir file
            if (unlink($path)) {
                $module->emDebug("File " . $path . " has been DELETED.");
            }
        }
        return $tips;


    }

    private function loadFile($path) {
        global $module;
        // Verify file exists
        if (file_exists($path)) {
            $contents = file($path);
            $module->emDebug($contents[0], $contents[1], $contents[2]);

            /**
            foreach ($contents as $row => $val) {

                //there seems to be some empty rows:
                if (empty(current($row))) {
                    continue;
                };

                if ($i > 0) {
                    $results[] = $row;
                }
                $i++;
            };
            */
            //$module->emDebug($contents, get_class($contents));
            $arranged = array();
            foreach ($contents as $row) {
                $item = str_getcsv(",", $row);
                $module->emDebug($item);

                $foo['eng'] = $item[1];        //1 is english
                $foo['esp'] = $item[2];        //2 is spanish
                $arranged[$item[0]][] = $foo;  //0 is the day number
            }

            return $arranged;

        } else {
            $module->emError("Unable to locate file $path");
        }
        return false;
    }

    // Process a text file and convert it into the sms_config
    function loadTipsFile($file)
    {
        global $module;
        //$file =  $module->getModulePath() . "docs/". $this->filename;


        ini_set("auto_detect_line_endings", 1);
        $handle = fopen($file, "r");
        $i = 0;
        $results = array();
        while ($row = fgetcsv($handle)) {

          //there seems to be some empty rows:
          if (empty(current($row))) {
            continue;
          };

            if ($i > 0) {
                $results[] = $row;
            }
            $i++;
        };

        fclose($handle);
        //$module->emDebug($file, $results);

        //keep the original form for dumping
        $this->original = $results;

        //rearrange so that the key is day
        //since there are cases of multiple texts in a day arrange
        //[key] : array ( array ('eng', 'esp' ) )
        $arranged = array();
        foreach ($results as $item) {
            $foo['eng'] = $item[1];        //1 is english
            $foo['esp'] = $item[2];        //2 is spanish
            $arranged[$item[0]][] = $foo;  //0 is the day number
        }

        //$module->emDebug($arranged); exit;
        return $arranged;
    }

    /**
     * Return the message for the supplied day and lang or null if invalid
     * @param $day
     * @param $lang
     * @return array|bool
     */
    public function getSmsForDay($day, $lang)
    {
        $this_msg = false;
        $this_day = $this->tipsArray[$day];

        foreach ($this_day as $item) {
            $this_msg[] = $item[$lang];
        }

        return $this_msg;
    }

    public function dumpCSV2() {
        if (empty($this->tipsArray)) {
            $this->tipsArray = self::loadTipsFile();
        }
        print "<pre>" . print_r($this->tipsArray,true). "</pre>";
    }

    public function dumpCSV() {
        //echo "<pre>".print_r($this->tipsArray, true)."</pre>";
        print "<table
  <tr>
    <th>Day number</th>
    <th>English</th>
    <th>Spanish</th>
  </tr>";
        foreach ($this->tipsArray as $dayNumber => $tips) {

            foreach ($tips as $k => $v) {
//                echo "<pre>" . print_r($v, true) . "</pre>";
                print "<tr>";
                print "<td>{$dayNumber}</td>";
                print "<td>{$v['eng']}</td>";
                print "<td>{$v['esp']}</td>";
            }
            print "</tr>";

        }
        print "</table>";

    }

}
