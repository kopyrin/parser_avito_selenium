#!/usr/bin/shell php
<?php
// как настраивать webDriver смотри здесь http://tceburashka.com/php-webdriver/#more-117

//указываем используемое пространство имен:
namespace Facebook\WebDriver;
//указываем какие классы будем использовать:
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Chrome\ChromeDriver; //add

// используем библиотеку для работы с электронной таблицей
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
//подключаем autoloader, сгенерированный для нас composer’ом
require_once('vendor/autoload.php');

class Parser
{
    private $parseCase, $baseHost, $baseUrl, $baseQuery = array();
    private $pageUrls = array(), $urlIndex = 0;
    private $driver, $dbId;
    private $name, $url, $pages;
    private $data_url         = "";
    private $data_name        = "";
    private $data_seller      = "";
    private $data_itemId      = "";
    private $data_description = "";
    private $data_price       = "";
    private $data_phone       = "";
    private $data_show        = "";
    private $data_show_today  = "";


    // конструктор при создании объекта
    public function __construct()
    {
        if (!$this->dbId = @mysqli_connect('localhost', 'root', '', 'avito2'))
            throw new Exception("MySQL: Unable to connect to database", self::CONNECT_ERROR);
        $this->dbId->Query("SET NAMES UTF8");

        $capabilities = DesiredCapabilities::chrome();
        $options = new ChromeOptions();
        $options->addArguments(array("headless=1"));
//        $options->addArguments(array("applicationCacheEnabled=1"));
//        $options->addArguments(array("user-data-dir=" . Config::get("BROWSER_DATA_DIR")));
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub',$capabilities, 5000);
//        $this->driver = RemoteWebDriver::create('http://localhost:4444/wd/hub', DesiredCapabilities::chrome(), 5000);
    }

    public function parse()
    {
        global $argv, $argc;
        // если аргументов меньше 4 выходим с сообщением
        if ($argc != 4) die("Usage parser.php case name-url count-pages" . PHP_EOL);

        // пропертису parseCase присваеваем значение стандартный класс
        //$this->parseCase = new stdClass();
        // и на лету создаем его свойства и присваеваем им значения из аргументов переданных из запуска программы
        // имя
        $this->name = $argv[1];
        // путь
        $this->url = $argv[2];
        // количество страниц которые нужно обойти в списке
        $this->pages = $argv[3];

        // разбираем url создаем массив urlParts в котором лежат части пути
        // путь здесь попадает из аргументов при запуске программы
        $urlParts = parse_url($this->url);

        $this->baseHost = (isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : null) . $urlParts['host'];

        $this->baseUrl = $this->baseHost . $urlParts['path'];

        // если есть параметры они разбираются в массив $this->baseQuery
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $this->baseQuery);
        }

        // проверяем, есть ли записи  в таблице (т.е. произошел сбой в работе парсера)
        $query = "SELECT * FROM ads ";
        $result =  mysqli_query($this->dbId, $query);

        if (mysqli_num_rows($result) == 0) {
            // начинаем обходить страницы списка начиная с 1 по $this->parseCase->pages
            $count_page = 1;

            while ( $count_page <= $this->pages ) {
                if ($count_page > 1) {
                    $this->url = $argv[2] . "?p=" . $count_page;
                }

                //обрабатываем список товаров на странице
                while ($url = $this->get()) {
                    $this->data_url = $url;

                    $query = "INSERT IGNORE INTO `ads` (`url`)
                                                VALUES ('" . $this->data_url . "')";

                    $this->dbId->Query($query);

                }
                $count_page++;
            }
        }

        $run = 1;
        while ($run !=0){
            echo "\n\rЗапись №  " . $run;
            $run++;
            if ($run > 6000){
                break;
            }
            // есть ли записи в таблице у которых не заполнен заголовок
            $query = "SELECT * FROM ads where id = " . $run;
            $result =  mysqli_query($this->dbId, $query);

            // если записей больше нет
            if (mysqli_num_rows($result) == 0){
                continue ;
            }

            $row = mysqli_fetch_row($result);
            // если записи есть но они заполнены ранее
            if ($row[4] !== "") {
                continue ;
            }

            //грузим данные из инета по конкретному товару
            try {
                $this->driver->navigate()->to($row[1]);
            } catch (WebDriverException $e){
                echo " Не загрузилась страница " . $row[1] . "\n\r";
                continue ;
            }
            $this->data_url = $row[1];

            echo "\n\rНазвание лота ";
            try {
                $this->data_name = $this->driver->findElement(WebDriverBy::cssSelector('span.title-info-title-text'))->getText();
            } catch (WebDriverException $e) {
                $this->data_name = "";
            }
            echo $this->data_name . "\n\r";

            echo "Продавец ";
            try {
                $this->data_seller = $this->driver->findElement(WebDriverBy::cssSelector('div.seller-info-name.js-seller-info-name'))->findElement(WebDriverBy::cssSelector('a'))->getText();
            } catch (WebDriverException $e) {
                $this->data_seller = "";
            }
            echo $this->data_seller . "\n\r";

            echo "Описание ";
            try {
                $this->data_description = $this->driver->findElement(WebDriverBy::className('item-description-text'))->getText();
            } catch (WebDriverException $e) {
                $this->data_description = "";
            }
            echo $this->data_description . "\n\r";

            echo "Количество просмотров всего ";
            try {
                $data = $this->driver->findElement(WebDriverBy::className('js-show-stat'))->getText();
                $this->data_show = Substr($data,0,  strpos($data,'(')-1);

                $start = strpos($data,'+')+1;
                $end   = strpos($data,')');
                $length =  $end - $start;

                $this->data_show_today = Substr($data,$start,$length);

            } catch (WebDriverException $e){
                $this->data_show = "" ;
                $this->data_show_today ="";
            }

            echo $this->data_show . "\n\r";

            echo "Количество просмотров сегодня ";
            echo $this->data_show_today . "\n\r";

            echo "Цена ";
            try {
                $this->data_price = $this->driver->findElement(WebDriverBy::className('js-item-price'))->getAttribute('content');
            } catch (WebDriverException $e) {
                $this->data_price ="0.00";
            }
            echo $this->data_price . "\n\r";

            echo "№ объявления ";
            try {
                $this->data_itemId = $this->driver->findElement(WebDriverBy::className('item-view-search-info-redesign'))->getText();
                $this->data_itemId = substr($this->data_itemId, 3, strpos($this->data_itemId, ',')-3 );
            } catch (WebDriverException $e) {
                $this->data_itemId ="";
            }
            if (empty($this->data_itemId)){
                try {
                    $this->data_itemId = $this->driver->findElement(WebDriverBy::className('title-info-metadata-item'))->getText();
                    $this->data_itemId = substr($this->data_itemId, 3, strpos($this->data_itemId, ',')-3 );
                } catch (WebDriverException $e) {
                    $this->data_itemId ="";
                }

            }
            echo $this->data_itemId  . "\n\r";

//                echo "Телефон продавца ";
//                $this->data_phone = $this->getAvitoPhone( $this->data_url);



            $query = "UPDATE `ads` SET
                       `item_id`    = '" . $this->data_itemId      . "',
                       `name`       = '" . $this->data_name        . "',
                       `seller`     = '" . $this->data_seller      . "',
                       `description`= '" . $this->data_description . "',
                       `price`      = '" . $this->data_price       . "',
                       `phone`      = '" . $this->data_phone       . "',
                       `show_today` = '" . $this->data_show_today  . "',
                       `show`       = '" . $this->data_show        . "'
                      WHERE `id`    = '" . $row[0]                 . "'";

            // заносим данные в таблицу
            $this->dbId->Query($query);
            mysqli_free_result($result);

        }


        echo "\n\rЗаношу данные в электронную таблицу \n\r ";

        $query = "SELECT * FROM ads";
        $result =  mysqli_query($this->dbId, $query);

        if ($result) {

            $sOutFile = $this->name.'.xlsx';

            $oSpreadsheet_Out = new Spreadsheet();

            $oSpreadsheet_Out->getProperties()->setCreator('Копырин Игорь')
                ->setLastModifiedBy('Копырин Игорь')
                ->setTitle('Office 2007 XLSX Test Document')
                ->setSubject('Office 2007 XLSX Test Document')
                ->setDescription('Результат парсинга AVito.ru')
                ->setKeywords('Avito.ru parsing')
                ->setCategory('Результат парсинга AVito.ru');

            // Add some data
            $oSpreadsheet_Out->setActiveSheetIndex(0)
                ->setCellValue('A1', 'URL')
                ->setCellValue('B1', '№')
                ->setCellValue('C1', 'Заголовок')
                ->setCellValue('D1', 'Продавец')
                ->setCellValue('E1', 'Описание')
                ->setCellValue('F1', 'Цена')
                ->setCellValue('G1', 'Телефон')
                ->setCellValue('H1', 'Показов всего')
                ->setCellValue('I1', 'Показов сегодня');

            $i=2;
            while ($row = mysqli_fetch_row($result)){
                    $oSpreadsheet_Out->setActiveSheetIndex(0)
                        ->setCellValue('A'. $i,  $row[1])
                        ->setCellValue('B'. $i,  $row[4])
                        ->setCellValue('C'. $i,  $row[2])
                        ->setCellValue('D'. $i,  $row[3])
                        ->setCellValue('E'. $i,  $row[5])
                        ->setCellValue('F'. $i,  $row[6])
                        ->setCellValue('G'. $i,  $row[7])
                        ->setCellValue('H'. $i,  $row[8])
                        ->setCellValue('I'. $i,  $row[9]);
                    $i++;
            }
            
            $oWriter = IOFactory::createWriter($oSpreadsheet_Out, 'Xlsx');
            $oWriter->save($sOutFile);
            //$oWriter->save('php://output');
            mysqli_free_result($result);
          }
        //$this->dbId->Query("TRUNCATE TABLE ads");
        $this->driver->quit();
    }

    function getAvitoPhone( $url )
    {
        $url = str_replace('https://www.', 'https://m.', $url);

        $this->driver->navigate()->to($url);
        try {
            $data = $this->driver->findElement(WebDriverBy::ClassName('_1DzgK'))->findElement(WebDriverBy::tagName('a'))->getAttribute('href');
            $data = '8'.substr($data, 6, 10 );
        } catch (WebDriverException $e) {
            $data = "";
        }
        echo $data . "\n\r";
        return $data;
    }

    /**
     * @return bool|mixed
     */
    public function get()
    {
        // если существует путь  - возвращаем значение элемента массива с url
        if ($this->hasNext()) {
            return $this->getNext();
        }

        // если массив путей пустой - заполняем массив URL
        if ($this->getNextPage() && $this->hasNext()) {
            // и возвращаем первый путь который будем парсить на этой странице
            return $this->getNext();
        }

        return false;
    }

    //если существует в сассиве по указанному индексу путь то возвращаем .T.
    private function hasNext()
    {
        return isset($this->pageUrls[$this->urlIndex]);
    }

    // что сейчас будем парсить
    /**
     * @return mixed
     */
    private function getNext()
    {
        $ret = $this->pageUrls[$this->urlIndex];
        $this->urlIndex++;
        return $ret;
    }

    private function getNextPage()
    {
        // массив Url товаров на текущей странице
        $this->pageUrls = array();
        // счетчик Url
        $this->urlIndex = 0;
        // если № текущей страницы больше количества страниц всего - уходим
        if ($this->page > $this->pages) return false;
        $query = http_build_query(
            array_merge(
                $this->baseQuery,
                $this->page == 1 ? array() : array('p' => $this->page)
            )
        );
        $url = $this->baseUrl . ($query ? '?' . $query : null);
        echo $url . "\r\n";

        $this->page++;

        $this->driver->navigate()->to($url);

        $hrefs = $this->driver->findElements(WebDriverBy::className('item-description-title-link'));
        foreach ($hrefs as $href){
            $this->pageUrls[] = $href->getAttribute('href');
        }
        return true;
    }

    private $parser;

    public function selfRun()
    {
        $parser = new parser();
        // запускаем метод parse()
        $parser->parse();
        // или запускаем выгрузку в xlsx файл
        //$parser->save_to_file();
    }

}


// запускаем метод selfRun
Parser::selfRun();

