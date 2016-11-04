<?php
//Подключаем класс парсера
require 'class/parser.php';
//Подключаем класс для работы базой данных
require 'class/db.php';
//Подключаем phpQuery
require 'phpQuery/phpQuery.php';

//Устанавливаем лимит на выполнении скрипта
set_time_limit(0);
//Выводим информацию в консоль
echo "Getting Started: ".date("Y-m-d H:m:s")."\n";
echo "Initialization...\n";

//Создаём объекты
$parser=new Parser();
$base=new Base();

//Получаем данные с ресурса, и выполняем парсинг данных
echo "Start Parsing...\n";
$parser->setMaxTime($base->getMaxTimePost());
$result=$parser->parserPost()->preparation();
echo "End Parsing.\n";

//Добавляем данные в базу данных
echo "Start Add Data Base...\n";
echo "Add Post...\n";
$count=$base->insertPost($result['sqlPost']);
echo "Add User...\n";
$base->insertUser($result['sqlAutor']);
echo "Add Foto...\n";
$base->insertFoto($result['sqlFoto']);
echo "End Add Data Base.\n";
echo "Start Load Foto...\n";
//Загружаем изображения на сервер
Base::loadFoto($result['sqlFotoLoad']);
echo "End Load Foto...\n";
echo "End of Work: ".date("Y-m-d H:m:s").".\n";
echo "Insert Post: ".$count.".\n";

//echo '<pre>';
//print_r($parser->getResult());
//print_r($result);
//echo '</pre>';

?>