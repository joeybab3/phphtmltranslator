<?php
    /*
    * HTML Translator
    * Translates HTML content using Google Translate via Stichoza's GoogleTranslate library
    *
    * @param $db - Database handle
    * @param $lang - Language to translate to
    *
    * Created: Joey Babcock - @joeybab3 - 2023-10
    */

    namespace Joeybab3\HTMLTranslator;

    use Stichoza\GoogleTranslate\GoogleTranslate;

    use DOMDocument;
    use DOMXPath;
    use PDO;

    class HTMLTranslator {
        private $lang;
        private $dbh;

        public function __construct($db, $lang = "en") {
            $this->lang = $lang;
            $this->dbh = $db;
        }

        public static function createTranslationCacheTable($db) {
            $sql = "CREATE TABLE `translations` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `text` text,
                `result` text,
                `lang` varchar(4) DEFAULT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        public function setLang($lang) {
            $this->lang = $lang;
        }

        public function getLang() {
            return $this->lang;
        }

        public function translate($text) {
            if($this->lang == "en") {
                return $text;
            }

            if(trim($text, " \t\n\r\0\x0B\xc2\xa0") == "" || $text == " ") {
                return $text;
            }

            // if output is only one character, don't translate
            if(strlen(trim($text, " \t\n\r\0\x0B\xc2\xa0")) == 1) {
                return $text;
            }

            // if output contains http:// or https://, don't translate
            if(strpos($text, "http://") !== false || strpos($text, "https://") !== false) {
                return $text;
            }

            $result = $this->checkCache(strtolower(trim($text, " \t\n\r\0\x0B\xc2\xa0")));

            if ($result) {
                return $result;
            } else {
                $tr = new GoogleTranslate($this->lang);
                $result = $tr->translate($text);
                sleep(3); // Google Translate API rate limiting to avoid getting banned (maybe)
                $this->addCacheEntry(strtolower(trim($text, " \t\n\r\0\x0B\xc2\xa0")), $result);
                return $result;
            }
        }

        public function checkCache($text) {
            $sql = "SELECT `result` FROM `translations` WHERE `text` = :text AND `lang` = :lang";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(':text' => $text, ':lang' => $this->lang));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['result'];
            } else {
                return false;
            }
        }

        public function addCacheEntry($text, $result) {
            $sql = "INSERT INTO `translations` (`text`, `lang`, `result`) VALUES (:text, :lang, :result)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute(array(':text' => $text, ':lang' => $this->lang, ':result' => $result));
        }

        public function tokenizedTranslate($string) {
            $old_string = $string;
            $new_string = mb_convert_encoding($string, 'HTML-ENTITIES', "UTF-8");
            $new_string = str_replace(chr(194) . chr(160), ' ', $new_string );
            $new_string = preg_replace('/\xc2\xa0/', '', $new_string);

            $textNodes = $this->getTokensFromHtml($new_string);

            foreach($textNodes as $text) {
                $content_text = $text->textContent;

                if(trim($content_text, " \t\n\r\0\x0B\xc2\xa0") == "" || $content_text == " ") {
                    continue;
                }

                $result = $this->translate($content_text);
                $old_string = str_replace($content_text, $result, $old_string);
            }

            return $old_string;
        }

        public function getTokensFromHtml($string) {
            $dom = new DOMDocument();
            @$dom->loadHTML($string);
            $xpath = new DOMXPath($dom);
            // get all text nodes, ignore script and style
            $query_no_script = "//*/text()[not(parent::script) and not(parent::style)]";
            $query = "//*[not(self::script or self::style)]/text()[normalize-space(.) != '']";
            $textNodes = $xpath->query($query);

            return $textNodes;
        }
    }