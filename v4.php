<?php

$script_minutes = 60;
$script_minutes += 5;
ini_set('max_execution_time', 60 * $script_minutes);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// если выводятся ошибки E_WARNING, то крашится генерация эксель файла, типа кодировка сбивается или еще что
// error_reporting(E_ERROR | E_WARNING | E_PARSE);
error_reporting(E_ERROR | E_PARSE);

require_once './PHPExcel-1.8/Classes/PHPExcel.php';

class Futures{

    // https://data.binance.vision/data/futures/um/monthly/klines/ADAUSDT/1m/ADAUSDT-1m-2023-10.zip
    public $url_mounts = 'https://data.binance.vision/data/futures/um/monthly/klines/';
    // https://data.binance.vision/data/futures/um/daily/klines/ADAUSDT/1m/ADAUSDT-1m-2023-11-02.zip
    public $url_day = 'https://data.binance.vision/data/futures/um/daily/klines/';
    public $dir_files = './files/';
    public $tmp_zip = 'tmp.zip';
    public $tmp_csv = 'tmp.csv';
    public $symbols = [];
    public $result = [];
    public $date_tmp = '';

    public $file_test_name = './file_test.csv';

    public $file_result = './result.csv';

    // bybit
    public $bybit_url = 'https://public.bybit.com/trading/';
    // okx
    public $okx_url = 'https://www.okx.com/cdn/okex/traderecords/trades/daily/';

    // начать с бинанса, потом байбит и т.д. доступные источники: binance, bybit, okx
    // sourse: binance, bybit, okx
    public $source_data = 'binance';
    // для тестирования конкретного источника
    // public $source_data_test = 'okx';
    public $source_data_test = false;

    public $script_minutes = 0;

    // файл для записи результата и переменные для него
    public $file_result_status_count = 'result_status_count.txt';
    public $file_result_status_symbols = 'result_status_symbols.txt';
    public $result_status_symbols_count = 0;
    

    function __construct($script_minutes) {
        $this->script_minutes = $script_minutes;
    }

    public function start(){

        file_put_contents($this->file_test_name, "");
        file_put_contents($this->file_test_name, "date;medium;low;hight" . PHP_EOL);

        $this->symbols = $this->get_config();

        // echo "<pre>";
        // var_dump($this->symbols);
        // echo "</pre>";
        // die;

        // начинаем писать файл для отображения работы скрипта онлайн
        $this->get_symbols_count();
        file_put_contents($this->file_result_status_symbols, "");
        file_put_contents($this->file_result_status_count, "");
        file_put_contents($this->file_result_status_count, "0" . PHP_EOL);


        $now = new DateTime();

        $key_status = 1;
        foreach($this->symbols as $symbol_conf){

            $this->result[$symbol_conf['key']] = $symbol_conf;
            $this->result[$symbol_conf['key']]['date_open'] = $symbol_conf['date_open']->format("d.m.Y H:i");
            

            if(!$symbol_conf['status'])
                continue;

            $symbol = $symbol_conf['symbol'] . 'USDT';
            $this->date_tmp = clone $symbol_conf['date_open'];

            // сдвинем старт на день назада, чтобы нивелировать разное начало дня на разных биржах, например у нас время старта в 00:30, а файл дня начинаетсья с 03:00
            $this->date_tmp->modify('-1 day');

            

            $this->source_data = 'binance';
            if($this->source_data_test){
                $this->source_data = $this->source_data_test;
            }

            $next = true;
            while($next){

                $now_new = new DateTime();

                if($now->getTimestamp() + $this->script_minutes * 60 < $now_new->getTimestamp() + 5*60){

                    $this->result[$symbol_conf['key']]['comment'] = "Не хватило времени.";
                    break;
                }


                $date = $this->date_tmp;                

                if($date->getTimestamp() > $now->getTimestamp()){

                    $this->result[$symbol_conf['key']]['comment'] = "Дата не может превышать нынешнюю.";
                    break;
                }


                // binance work
                // echo $date->format("d.m.Y H:i") . '<br>';

                if($this->source_data == 'binance'){
                    // echo "binance" . '<br>';

                    $have_file = $this->get_archive($date, $symbol);
                    // $have_file = false;
                    if(!$have_file){
                        // $this->result[$symbol_conf['key']]['comment'] = "Монета не найдена.";
                        // break;

                        $this->source_data = 'bybit';
                    }else{
                        $this->result[$symbol_conf['key']]['source'] = 'binance';
                        $this->unzip();
                    }
                    
                }
                
                if($this->source_data == 'bybit'){

                    // bybit
                    // echo "test bybit" . "<br>";

                    // file_put_contents($this->dir_files . $this->tmp_csv, 'Open time;Open;High;Low;Close' . PHP_EOL, LOCK_EX);

                    $status = $this->check_bybit($date, $symbol);

                    if(!$status){
                        // $this->result[$symbol_conf['key']]['comment'] = "Монета не найдена. bybit";
                        // break;
                        $this->source_data = 'okx';
                    }else{
                        $this->result[$symbol_conf['key']]['source'] = 'bybit';
                    }
                    // die;
                    // echo $status;
                }

                if($this->source_data == 'okx'){
                    $status = $this->check_okx($date, $symbol_conf['symbol']);

                    if(!$status){
                        $this->result[$symbol_conf['key']]['comment'] = "Монета не найдена. okx";
                        break;
                    }else{
                        $this->result[$symbol_conf['key']]['source'] = 'okx';
                    }

                    // echo 'adgrg';
                    // die;
                }


                $this->read($symbol_conf);



                if($this->result[$symbol_conf['key']]['date_close']){

                    $next = false;
                }else{

                    $this->date_tmp = $this->get_next_date($this->date_tmp, $now);

                    if(!$this->date_tmp){
                        $this->result[$symbol_conf['key']]['comment'] = "Результат не найден.";
                        $next = false;
                    }   
                }
            }

            // обновляем статус скрипта
            $status_count_tmp = round($key_status / ($this->result_status_symbols_count / 100));
            // пишем прогресс в процентах
            file_put_contents($this->file_result_status_count, $status_count_tmp);
            // дописываем сделанные пары
            file_put_contents($this->file_result_status_symbols, $symbol_conf['symbol'] . PHP_EOL, FILE_APPEND);
            $key_status++;

            $this->generate_csv();
        }

        // $this->generate_exel();
        $this->generate_csv();

        // пишем, что скрипт закончил
        file_put_contents($this->file_result_status_count, 'end');

        return $this->result;
    }

    protected function get_next_date($date, $now){
        // echo "get_next_date<br>";

        if($this->source_data == 'binance'){

            if($date->format("Y") < $now->format("Y") || ($date->format("Y") == $now->format("Y") && $date->format("m")+1 <= $now->format("m"))){
                $date->modify( 'first day of next month' );
            }else{
    
                $date->modify('+1 day');
            }
        // если байбит то только по дням можем пока двигаться
        }elseif($this->source_data == 'bybit'){

            $date->modify('+1 day');
        }elseif($this->source_data == 'okx'){

            $date->modify('+1 day');
        }
        
        if($date->getTimestamp() + 60*60*24*2 > $now->getTimestamp()){
            $date = false;
        }

        // echo "<pre>";
        // var_dump($date);
        // echo "</pre>";

        return $date;
    }

    protected function get_config(){

        $excel = PHPExcel_IOFactory::load('./config.xlsx');
        $worksheet = $excel->getSheet(0);
        // $worksheet->getStyle('E1:E3000')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
        $lastRow = $worksheet->getHighestRow();

        $symbols = [];
        $x = 0;
        for ($row = 1; $row <= $lastRow; $row++) {
            if($row > 1){
                
                $date_open = new DateTime($worksheet->getCell('B'.$row)->getFormattedValue());

                // на всякий случай зададим формат при создании, чтобы в будущем не накосипорить
                // !!!!! не надо это делать, т к из экселя уже в нужном виде дата достается
                // $date_open_tmp = $worksheet->getCell('A'.$row)->getFormattedValue();
                // echo $date_open_tmp . '<br>';
                // $date_open = DateTime::createFromFormat("d.m.Y h:i:s", $date_open_tmp);
                // echo $date_open->format("d.m.Y H:i") . '<br>';
                // die;

                $symbol = $worksheet->getCell('C'.$row)->getValue();
                $type = $worksheet->getCell('D'.$row)->getValue();
                $target_1 = $worksheet->getCell('K'.$row)->getValue();

                if($date_open && $symbol && $type && $target_1){

                    $symbols[$x]['status'] = true;
                    $symbols[$x]['comment'] = '';
                }else{

                    $symbols[$x]['status'] = false;
                    $symbols[$x]['comment'] = 'Ошибка! Одно или несколько обязательных полей не заполнены (время, инструмент, направление, тп1)';
                }
                // ссылка
                // время
                // инструмент
                // направление
                // твх
                // уср2
                // уср4
                // уср8
                // уср16
                // итог твх
                // тп1
                // тп2
                // тп3
                // тп4
                // тп5
                // тп6
                // тп7
                // // стоплосс
                // сл
                // тп закрытия1
                // тп закрытия2
                // просадка1
                // просадка2
                // срок1
                // срок2
                $symbols[$x]['key'] = $x;

                $symbols[$x]['url'] = $worksheet->getCell('A'.$row)->getValue();

                $symbols[$x]['date_open'] = $date_open;

                // для лимитных ордеров, тк старт там не по времени, а по цене
                $symbols[$x]['date_start'] = $date_open;

                $symbols[$x]['symbol'] = strtoupper(trim($symbol));
                $symbols[$x]['type'] = trim($type);
                $symbols[$x]['point_start'] = $worksheet->getCell('E'.$row)->getValue();

                $symbols[$x]['middle_2'] = $worksheet->getCell('F'.$row)->getValue();
                // $symbols[$x]['middle_2'] = $worksheet->getCell('E'.$row)->getFormattedValue();
                
                $symbols[$x]['middle_4'] = $worksheet->getCell('G'.$row)->getValue();
                $symbols[$x]['middle_8'] = $worksheet->getCell('H'.$row)->getValue();
                $symbols[$x]['middle_16'] = $worksheet->getCell('I'.$row)->getValue();
                $symbols[$x]['point_start_result'] = 0;
                $symbols[$x]['target_1'] = $target_1;
                $symbols[$x]['target_2'] = $worksheet->getCell('L'.$row)->getValue();
                $symbols[$x]['target_3'] = $worksheet->getCell('M'.$row)->getValue();
                $symbols[$x]['target_4'] = $worksheet->getCell('N'.$row)->getValue();
                $symbols[$x]['target_5'] = $worksheet->getCell('O'.$row)->getValue();
                $symbols[$x]['target_6'] = $worksheet->getCell('P'.$row)->getValue();
                $symbols[$x]['target_7'] = $worksheet->getCell('Q'.$row)->getValue();
                $symbols[$x]['stop_loss'] = $worksheet->getCell('R'.$row)->getValue();
                // дефолтное значение, чтобы сохранить
                $symbols[$x]['stop_loss_def'] = $worksheet->getCell('R'.$row)->getValue();
                $symbols[$x]['target_close_1'] = 0;
                $symbols[$x]['target_close_2'] = 0;

                // проверяем статус цели, если попали на вышестоящую ель, нижестоящие уже не отрабатываются
                $symbols[$x]['target_status'] = 0;

                $symbols[$x]['min_1'] = 0;
                $symbols[$x]['min_2'] = 0;
                $symbols[$x]['period_1'] = 0;
                $symbols[$x]['period_2'] = 0;

                // test
                $symbols[$x]['date_target_1'] = 0;
                $symbols[$x]['date_target_2'] = 0;

                $symbols[$x]['date_close'] = false;
                $symbols[$x]['min_1_status'] = false;
                $symbols[$x]['min_2_status'] = false;

                // задел ли график первое усреднение или первую цель
                $symbols[$x]['point_start_result_status'] = false;
                
                $symbols[$x]['order_status'] = false;
                // сюда пишем не цену закрытия а цифру достигнутой цели или 0 - stop loss или "в работе если еще болтается"
                $symbols[$x]['target_close_1_type'] = 0;

                $x++;
            }
        }

        return $symbols;
    }

    protected function get_archive($date, $symbol){
        
        $status = false;

        $now = new DateTime();
        // проверяем есть ли файл месяца или нам придется перебирать дни
        if($date->format("Y") < $now->format("Y") || ($date->format("Y") == $now->format("Y") && $date->format("m") < $now->format("m"))){
    
            $url = $this->url_mounts . $symbol . '/1m/' . $symbol . '-1m-' . $date->format("Y") . '-' . $date->format("m") . '.zip';
        }else{
    
            $url = $this->url_day . $symbol . '/1m/' . $symbol . '-1m-' . $date->format("Y") . '-' . $date->format("m") . '-' . $date->format("d") . '.zip';
        }

        // echo $url . '<br>';
    
        $file = file_get_contents($url);
        if($file){
            file_put_contents($this->dir_files . $this->tmp_zip, file_get_contents($url));
            $status = true;
        }
        // file_put_contents($this->dir_files . $this->tmp_zip, file_get_contents($url));
    
        return $status;
    }

    protected function check_bybit($date, $symbol){

        // echo 'check_bybit' . '<br>';

        $status = false;
        $url = $this->bybit_url . $symbol . '/' . $symbol . $date->format("Y") . '-' . $date->format("m") . '-' . $date->format("d") . '.csv.gz';
        
        // echo $url . '<br>';
        // die;

        // result[0] - тело, result[1] - заголовки
        $result = $this->get_fcontent($url);

        // echo '<pre>';
        // var_dump($result[1]);
        // echo '</pre>';
        // die;

        if($result[1]['http_code'] == 200){
            // file_put_contents($this->dir_files . $this->tmp_zip, $result[0]);
            file_put_contents($this->dir_files . 'tmp.csv.gz', $result[0]);
            $status = true;
            
            $lines = gzfile($this->dir_files . "tmp.csv.gz");
            // echo $lines[0];
            // 0 - timestamp, 4 - price
            $date = false;

            // массив с результатом
            $result_arr = [];

            $x = 0;
            foreach ($lines as $k => $line) {

                if($k == 0)
                    continue;

                $cols = explode(",", $line);
                $timestamp = (int)$cols[0];
                $price = $cols[4];

                // если первая строка, создаем дату первой свечи
                if(!$date){
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);

                    // echo $date->getTimestamp() . '<br>';

                    $second = $date->format('s');
                    $date->sub(new DateInterval("PT". $second ."S"));
                    // $date->sub(new DateInterval("PT1S"));

                    // echo $date->getTimestamp() . '<br>';
                    // break;

                    $price_open = $price;
                    $price_close = $price;
                    $price_hight = $price;
                    $price_low = $price;
                }

                // если новая минута
                if($timestamp >= $date->getTimestamp() + 60){
                    // запишем предыдущую свечу
                    $result_arr[$x]['open_time'] = $date->getTimestamp() * 1000;
                    $result_arr[$x]['price_open'] = $price_open;
                    $result_arr[$x]['price_close'] = $price_close;
                    $result_arr[$x]['price_hight'] = $price_hight;
                    $result_arr[$x]['price_low'] = $price_low;

                    $x++;


                    // если это след минута
                    if($timestamp < $date->getTimestamp() + 60*2){
                        // обновим дату
                        $date = new DateTime();
                        $date->setTimestamp($timestamp);
                        $second = $date->format('s');
                        $date->sub(new DateInterval("PT". $second ."S"));

                        // обновим цены
                        $price_open = $price;
                        $price_close = $price;
                        $price_hight = $price;
                        $price_low = $price;

                    }else{
                        // если у нас не было ордеров больше минуты, то будут пустые свечи (типа блинцы), создадим их
                        // получается что у нас промежуток между ордерами больше минуты

                        while(true){

                            $date->add(new DateInterval("PT60S"));

                            // все цены равняются цене закрытия последней свечи, т к после других ордеров не было
                            $result_arr[$x]['open_time'] = $date->getTimestamp() * 1000;
                            $result_arr[$x]['price_open'] = $price_close;
                            $result_arr[$x]['price_close'] = $price_close;
                            $result_arr[$x]['price_hight'] = $price_close;
                            $result_arr[$x]['price_low'] = $price_close;

                            $x++;

                            // если мы дошши до минуты, в которой текущий ордер
                            if($timestamp < $date->getTimestamp() + 60){
                                // обновим дату
                                $date = new DateTime();
                                $date->setTimestamp($timestamp);
                                $second = $date->format('s');
                                $date->sub(new DateInterval("PT". $second ."S"));

                                // обновим цены
                                $price_open = $price;
                                $price_close = $price;
                                $price_hight = $price;
                                $price_low = $price;

                                // выйдем из цикла
                                break;
                            }
                        }
                    }
                    

                }else{
                    // если та же минута просто перезапсываем цены если надо
                    // цену закрытия всегда перезаписываем
                    $price_close = $price;

                    if($price_hight < $price){
                        $price_hight = $price;
                    }
                    if($price_low > $price){
                        $price_low = $price;
                    }
                }

                // 0    Open time
                // 1    Open
                // 2    High
                // 3    Low
                // 4    Close

                // if($k > 100){
                //     break;
                // }
            }

            // пишем результат в файл
            // file_put_contents('./files/tmp_test.csv', 'Open time;Open;High;Low;Close' . PHP_EOL, LOCK_EX);
            file_put_contents($this->dir_files . $this->tmp_csv, 'Open time,Open,High,Low,Close' . PHP_EOL, LOCK_EX);
            foreach($result_arr as $r){
                // file_put_contents('./files/tmp_test.csv', $r['open_time'] . ';' . $r['price_open'] . ';' . $r['price_hight'] . ';' . $r['price_low'] . ';' . $r['price_close'] . PHP_EOL, FILE_APPEND | LOCK_EX);
                file_put_contents($this->dir_files . $this->tmp_csv, $r['open_time'] . ',' . $r['price_open'] . ',' . $r['price_hight'] . ',' . $r['price_low'] . ',' . $r['price_close'] . PHP_EOL, FILE_APPEND | LOCK_EX);
            }
            
        }

        return $status;
    }

    protected function check_okx($date, $symbol){
        $status = true;
        // формируем адрес где лежт архив
        $url = $this->okx_url . $date->format("Y") . $date->format("m") . $date->format("d") . '/' . $symbol . '-USDT-trades-' . $date->format("Y") . '-' . $date->format("m") . '-' . $date->format("d") . '.zip';
        
        // echo $url . '<br>';

        $result = $this->get_fcontent($url);

        // echo "<pre>";
        // var_dump($result[1]);
        // echo "</pre>";

        if($result[1]['http_code'] == 200){
            // кладем архив в нужное место с нужным именем
            file_put_contents($this->dir_files . $this->tmp_zip, $result[0]);

            $zip = new ZipArchive;
            $res = $zip->open($this->dir_files . $this->tmp_zip);
            if ($res === TRUE) {
        
                // переименованный в стандартный временный файл, чтобы прочитать его и перезаписать
                $name_tmp = $zip->getNameIndex(0);
        
                // путь к каталогу, в который будут помещены файлы
                $zip->extractTo($this->dir_files);
                $zip->close();
        
                rename($this->dir_files . $name_tmp, $this->dir_files . 'tmp_okx.csv');
                
                $status = $this->generate_kline_okx();


            }else{
                $status = false;
            }
        }else{
            $status = false;
        }

        return $status;

    }

    protected function generate_kline_okx(){
        $status = true;
        if (($handle = fopen($this->dir_files . 'tmp_okx.csv', "r")) !== FALSE) {

            $result_arr = [];
            $k = 0;
            $x = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $k++;
                if($k == 1)
                    continue;
                
                $timestamp = (int)($data[4] / 1000);
                $price = $data[3];

                // если первая строка, создаем дату первой свечи
                if(!$date){
                    $date = new DateTime();
                    $date->setTimestamp($timestamp);
                    $second = $date->format('s');
                    $date->sub(new DateInterval("PT". $second ."S"));

                    $price_open = $price;
                    $price_close = $price;
                    $price_hight = $price;
                    $price_low = $price;
                }

                // если новая минута
                if($timestamp >= $date->getTimestamp() + 60){
                    // запишем предыдущую свечу
                    $result_arr[$x]['open_time'] = $date->getTimestamp() * 1000;
                    $result_arr[$x]['price_open'] = $price_open;
                    $result_arr[$x]['price_close'] = $price_close;
                    $result_arr[$x]['price_hight'] = $price_hight;
                    $result_arr[$x]['price_low'] = $price_low;

                    $x++;

                    // если это след минута
                    if($timestamp < $date->getTimestamp() + 60*2){
                        // обновим дату
                        $date = new DateTime();
                        $date->setTimestamp($timestamp);
                        $second = $date->format('s');
                        $date->sub(new DateInterval("PT". $second ."S"));

                        // обновим цены
                        $price_open = $price;
                        $price_close = $price;
                        $price_hight = $price;
                        $price_low = $price;

                    }else{
                        // если у нас не было ордеров больше минуты, то будут пустые свечи (типа блинцы), создадим их
                        // получается что у нас промежуток между ордерами больше минуты

                        while(true){

                            $date->add(new DateInterval("PT60S"));

                            // все цены равняются цене закрытия последней свечи, т к после других ордеров не было
                            $result_arr[$x]['open_time'] = $date->getTimestamp() * 1000;
                            $result_arr[$x]['price_open'] = $price_close;
                            $result_arr[$x]['price_close'] = $price_close;
                            $result_arr[$x]['price_hight'] = $price_close;
                            $result_arr[$x]['price_low'] = $price_close;

                            $x++;

                            // если мы дошши до минуты, в которой текущий ордер
                            if($timestamp < $date->getTimestamp() + 60){
                                // обновим дату
                                $date = new DateTime();
                                $date->setTimestamp($timestamp);
                                $second = $date->format('s');
                                $date->sub(new DateInterval("PT". $second ."S"));

                                // обновим цены
                                $price_open = $price;
                                $price_close = $price;
                                $price_hight = $price;
                                $price_low = $price;

                                // выйдем из цикла
                                break;
                            }
                        }
                    }
                    

                }else{
                    // если та же минута просто перезапсываем цены если надо
                    // цену закрытия всегда перезаписываем
                    $price_close = $price;

                    if($price_hight < $price){
                        $price_hight = $price;
                    }
                    if($price_low > $price){
                        $price_low = $price;
                    }
                }
            }

            // пишем результат в файл
            file_put_contents($this->dir_files . $this->tmp_csv, 'Open time,Open,High,Low,Close' . PHP_EOL, LOCK_EX);
            foreach($result_arr as $r){
                file_put_contents($this->dir_files . $this->tmp_csv, $r['open_time'] . ',' . $r['price_open'] . ',' . $r['price_hight'] . ',' . $r['price_low'] . ',' . $r['price_close'] . PHP_EOL, FILE_APPEND | LOCK_EX);
            }

        }else{

            $status = fasle;
        }
        return $status;
    }

    protected function unzip(){

        $zip = new ZipArchive;
        $res = $zip->open($this->dir_files . $this->tmp_zip);
        if ($res === TRUE) {
    
            // переименованный в стандартный временный файл, чтобы прочитать его и перезаписать
            $name_tmp = $zip->getNameIndex(0);
    
            // путь к каталогу, в который будут помещены файлы
            $zip->extractTo($this->dir_files);
            $zip->close();
    
            rename($this->dir_files . $name_tmp, $this->dir_files . $this->tmp_csv);
    
        } else {
            echo "Ошибка unzip()";
            die;
        }
    }

    protected function get_period($date_open, $date_tmp){
        $date_close = new DateTime();
        $date_close->setTimestamp($date_tmp);

        $interval = new DateInterval('PT1H');
        $periods = new DatePeriod($date_open, $interval, $date_close);
        $hours = iterator_count($periods);

        if($hours == 0){
            $hours = 1;
        }

        return $hours;
    }

    protected function write_test_data($date, $price_medium, $price_low, $price_hight){

        $date_tmp = new DateTime();
        $date_tmp->setTimestamp($date);

        file_put_contents($this->file_test_name, $date_tmp->format("d.m.Y H:i") . ';' . $price_medium . ';' . $price_low . ';' . $price_hight .  PHP_EOL, FILE_APPEND);
    }

    protected function generate_csv(){

        // $file_name = './result.csv';
        $file_name = $this->file_result;
        
        file_put_contents($file_name, "");

        $head = '';
        $head .= 'ссылка;';
        $head .= 'время;';
        $head .= 'инструмент;';
        $head .= 'направление;';
        $head .= 'твх;';
        $head .= 'уср2;';
        $head .= 'уср4;';
        $head .= 'уср8;';
        $head .= 'уср16;';
        $head .= 'итог твх;';
        $head .= 'тп1;';
        $head .= 'тп2;';
        $head .= 'тп3;';
        $head .= 'тп4;';
        $head .= 'тп5;';
        $head .= 'тп6;';
        $head .= 'тп7;';
        $head .= 'сл def;';
        $head .= 'сл;';
        $head .= 'тп закрытия тип;';
        $head .= 'тп закрытия;';
        // $head .= 'тп закрытия2;';
        $head .= 'просадка;';
        // $head .= 'просадка2;';
        $head .= 'срок;';
        // $head .= 'срок2;';
        $head .= 'коммент;';
        $head .= 'источник';

        // test
        $head .= ';дата старт';
        $head .= ';дата1';
        $head .= ';дата2';

        // $head = mb_convert_encoding($head, "ANSI");
        $head = mb_convert_encoding($head, 'windows-1251', 'utf-8');

        file_put_contents($file_name, $head . PHP_EOL, FILE_APPEND);

        foreach($this->result as $str){
            $str_tmp = '';
            if(!$str['status']){
                $str_tmp .= ';;;;;;;;;;;;;;;;;;;;;;;' . $str['comment'] . ';';

                // test
                $str_tmp .= ';;;';
            }else{
                $str_tmp .= $str['url'] . ';';
                $str_tmp .= $str['date_open'] . ';';
                $str_tmp .= $str['symbol'] . ';';
                $str_tmp .= $str['type'] . ';';
                // $str_tmp .= $str['point_start'] . ';';
                $str_tmp .= str_replace('.', ',', $str['point_start']) . ';';
                // $str_tmp .= $str['middle_2'] . ';';
                $str_tmp .= str_replace('.', ',', $str['middle_2']) . ';';
                // $str_tmp .= $str['middle_4'] . ';';
                $str_tmp .= str_replace('.', ',', $str['middle_4']) . ';';
                // $str_tmp .= $str['middle_8'] . ';';
                $str_tmp .= str_replace('.', ',', $str['middle_8']) . ';';
                // $str_tmp .= $str['middle_16'] . ';';
                $str_tmp .= str_replace('.', ',', $str['middle_16']) . ';';
                // $str_tmp .= $str['point_start_result'] . ';';
                $str_tmp .= str_replace('.', ',', $str['point_start_result']) . ';';
                // $str_tmp .= $str['target_1'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_1']) . ';';
                // $str_tmp .= $str['target_2'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_2']) . ';';
                // $str_tmp .= $str['target_3'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_3']) . ';';
                // $str_tmp .= $str['target_4'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_4']) . ';';
                // $str_tmp .= $str['target_5'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_5']) . ';';
                // $str_tmp .= $str['target_6'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_6']) . ';';
                // $str_tmp .= $str['target_7'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_7']) . ';';
                // $str_tmp .= $str['stop_loss'] . ';';
                $str_tmp .= str_replace('.', ',', $str['stop_loss_def']) . ';';
                $str_tmp .= str_replace('.', ',', $str['stop_loss']) . ';';
                // $str_tmp .= $str['target_close_1'] . ';';
                $str_tmp .= $str['target_close_1_type'] . ';';
                $str_tmp .= str_replace('.', ',', $str['target_close_1']) . ';';
                // $str_tmp .= $str['target_close_2'] . ';';
                // $str_tmp .= str_replace('.', ',', $str['target_close_2']) . ';';
                // $str_tmp .= $str['min_1'] . ';';
                $str_tmp .= str_replace('.', ',', $str['min_1']) . ';';
                // $str_tmp .= $str['min_2'] . ';';
                // $str_tmp .= str_replace('.', ',', $str['min_2']) . ';';
                $str_tmp .= $str['period_1'] . ';';
                // $str_tmp .= $str['period_2'] . ';';
                $str_tmp .= $str['comment'] . ';';
                $str_tmp .= $str['source'];
                

                // test
                $str_tmp .= ';' . $str['date_start']->format("d.m.Y H:i");
                $str_tmp .= ';' . $str['date_target_1'];
                $str_tmp .= ';' . $str['date_target_2'];
            }
            $str_tmp = mb_convert_encoding($str_tmp, 'windows-1251', 'utf-8');
            file_put_contents($file_name, $str_tmp . PHP_EOL, FILE_APPEND);
        }

        // $str = file_get_contents($file_name);
        
        // $str = mb_convert_encoding($str, "ANSI");

        // file_put_contents($file_name, $str);
    }

    public function download_file(){

        $file = $this->file_result;
 
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file));

        readfile($file);
    }

    function get_fcontent( $url,  $javascript_loop = 0, $timeout = 5 ) {
        $url = str_replace( "&amp;", "&", urldecode(trim($url)) );
    
        // $cookie = tempnam ("/tmp", "CURLCOOKIE");
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
        curl_setopt( $ch, CURLOPT_URL, $url );
        // curl_setopt( $ch, CURLOPT_COOKIEJAR, $cookie );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_ENCODING, "" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_AUTOREFERER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );    # required for https urls
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
        $content = curl_exec( $ch );
        $response = curl_getinfo( $ch );
        curl_close ( $ch );
    
        if ($response['http_code'] == 301 || $response['http_code'] == 302) {
            ini_set("user_agent", "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
    
            if ( $headers = get_headers($response['url']) ) {
                foreach( $headers as $value ) {
                    if ( substr( strtolower($value), 0, 9 ) == "location:" )
                        return get_url( trim( substr( $value, 9, strlen($value) ) ) );
                }
            }
        }
    
        if (    ( preg_match("/>[[:space:]]+window\.location\.replace\('(.*)'\)/i", $content, $value) || preg_match("/>[[:space:]]+window\.location\=\"(.*)\"/i", $content, $value) ) && $javascript_loop < 5) {
            return get_url( $value[1], $javascript_loop+1 );
        } else {
            return array( $content, $response );
        }
    }

    public function get_symbols_count(){
        foreach($this->symbols as $s){
            if($s['symbol']){
                $this->result_status_symbols_count++;
            }
        }
    }

}

$futures = new Futures($script_minutes);
$result = $futures->start();

// $futures->download_file();

// echo "<pre>";
// var_dump($result);
// echo "</pre>";