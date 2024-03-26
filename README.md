# phphtmltranslator
PHP HTML Translator

Translates HTML into a destination language using Stichoza's Google Translate PHP:
https://github.com/Stichoza/google-translate-php

It has all the same limitations, namely that since it uses the free Google Translate API, you may run into rate limits or a temporary IP Ban.

It uses a SQL database to cache previously translated tokens so running html through it repeatedly with only minor changes will be much faster.

# Setup

`composer require joeybab3/phphtmltranslator`

Once installed run the following PHP somewhere:
```php
<?php
require_once('vendor/autoload.php');
use Joeybab3\HTMLTranslator\HTMLTranslator as Translator;

$dsn = "mysql:host=127.0.0.1;dbname=database_name;charset=$charset";
$username = "";
$password = "";
try {
    $db = new PDO($dsn, $username, $password);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

Translator::createTranslationCacheTable($db);
```

This will create the cache table. Alternatively, create the table yourself:
```sql
CREATE TABLE `translations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `text` text,
  `result` text,
  `lang` varchar(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

Then you can use it to translate HTML:
```php
<?php
require_once('vendor/autoload.php');
use Joeybab3\HTMLTranslator\HTMLTranslator as Translator;

$TR = new Translator($db, "es"); // translate to Spanish
$html = "<div class='test'><span>This text is not bold but <b>this text is bold</b></span><span>This text, on the other hand, will be a separate translation entirely.</span>";

$result = $TR->tokenizedTranslate($html);

// <div class='test'><span>Este texto no está en negrita pero <b>este texto está en negrita</b></span><span>Este texto, por otro lado, será una traducción completamente separada. </span>
```
