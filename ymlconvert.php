<?php
require_once(dirname(__FILE__) . '/' . 'bootstrap.php');
$xml = simplexml_load_file("pricelist.yml"); // Загружаем файл с ценами

// Title for csv
// Формируем массив заголовов для csv файла
$created_data = array(
  array(
    'CML GROUP ID идентификатор группы товаров',
    'CML ID идентификатор товара',
    'Название товара',
    'Текст для товара',
    'Файл изображения для товара',
    'Цена (до перевода по текущему курсу рубля)',
    'Название производителя',
    'Производительность',
    'Длина',
    'Ширина',
    'Высота',
    'Назначение',
    'Характеристика',
  )
);

// Делаем запрос в бд для получения всех не удаленных товаров
$oCore_DataBase = Core_DataBase::instance()
  ->setQueryType(0)
  ->query("SELECT guid, name FROM `shop_items` WHERE `deleted` = '0'");
$dbOffers = $oCore_DataBase->asAssoc()->result();

$i = 0;
// Проходимся по всем offer в yml каталоге
foreach ($xml->shop->offers->offer as $offer) {
  $i++;
  // Находим название категории
  $categoryItem = null;
  $categoryGuid = null;
  foreach ($xml->shop->categories->category as $row) {
    if (intval($row['id']) == $offer->categoryId) $categoryItem = $row;
  }
  // END
  $created_data[$i] = array("", "", "", "", "", "", "", "", "", "", "", "", ""); // Массив для заполнения
  // Получаем информацию о категории из бд по имени категории
  // Если категория не создана, создает ее и возвращает информацию
  $categoryName = strval($categoryItem);
  if ($categoryName == "Промышленные системы") $categoryName = "Промышленный осмос";
  $dbCategory = categoryGetDB($categoryName);
  // Получаем информацию о товаре из бд по имени товара
  // Если товар не создана, формирует guid
  $dbOffer = offerGetDB(strval($offer->name));
  $categoryGuid = $dbCategory->guid;
  if (!$dbOffer) {
    $offetGuid = guidCheck(Core_Guid::get(), $dbOffers); // Проверяем есть ли в бд товар с формулированным guid
  } else {
    $categoryData = getCategoryByID($dbOffer->shop_group_id);
    if ($categoryData) {
      $categoryGuid = $categoryData->guid;
      $offetGuid = $dbOffer->guid;
    }
  }
  $created_data[$i][0] = $categoryGuid;
  $created_data[$i][1] = $offetGuid;
  $created_data[$i][2] = $offer->name;
  $created_data[$i][3] = $offer->description;
  $created_data[$i][4] = $offer->picture;
  $created_data[$i][5] = $offer->price;
  $cherecters = array();
  foreach ($offer->param as $param) {
    $paramName = strval($param["name"]);
    $paramVal = strval($param);
    switch ($paramName) {
      case "Производитель":
        $created_data[$i][6] = $paramVal;
        break;
      case "Производительность max":
        $created_data[$i][7] = $paramVal;
        break;
      case "Длина":
        $created_data[$i][8] = $paramVal;
        break;
      case "Ширина":
        $created_data[$i][9] = $paramVal;
        break;
      case "Высота":
        $created_data[$i][10] = $paramVal;
        break;
      case "Назначение (фильтра)":
        $created_data[$i][11] = $paramVal;
        break;
      default:
        $cherecters[] = array($paramName, $paramVal);
    }
  }
  foreach ($cherecters as $key => $ch) {
    if ($key == 0) $created_data[$i][12] = "<td>{$ch[0]}</td><td>{$ch[1]}</td>";
    else {
      $i++;
      $created_data[$i] = array(
        $categoryGuid,
        $offetGuid,
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        "<td>{$ch[0]}</td><td>{$ch[1]}</td>",
      );
    }
  }
}

array_to_csv_download($created_data);

function array_to_csv_download($array, $filename = "pricelist.csv", $delimiter = ";")
{
  // open raw memory as file so no temp files needed, you might run out of memory though
  $f = fopen('php://memory', 'w');
  // fputs($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM
  // loop over the input array
  foreach ($array as $line) {
    // generate csv lines from the inner arrays
    fputcsv($f, $line, $delimiter);
  }
  // reset the file pointer to the start of the file
  fseek($f, 0);
  // tell the browser it going to be a csv file
  header('Content-Type: application/csv');
  // tell the browser we want to save it instead of displaying it
  header('Content-Disposition: attachment; filename="' . $filename . '";');
  // make php send the generated csv lines to the browser
  fpassthru($f);
  fclose($f);
}

// Function check category with $catName in DB, if no create
// Функция проверяет есть ли категория с текущим именем, Если нету то создает
function categoryGetDB($catName = null)
{
  $oShopGroup = Core_Entity::factory('Shop_Group');
  $oShopGroup
    ->queryBuilder()
    ->where('deleted', '=', '0');
  if (!empty($catName)) $oShopGroup->queryBuilder()->where('name', '=', $catName);
  $aShopGroups = $oShopGroup->findAll(FALSE);
  if (empty($aShopGroups)) {
    $guid = Core_Guid::get();
    $oShopGroup->guid = $guid;
    $oShopGroup->shop_id = 1;
    $oShopGroup->name = strval($catName);
    $oShopGroup->save();
    return $oShopGroup;
  } else {
    return $aShopGroups[0];
  }
}

// Get category by ID
// Получение категории по ID
function getCategoryByID($catID)
{
  $oShopGroup = Core_Entity::factory('Shop_Group');
  $oShopGroup
    ->queryBuilder()
    ->where('deleted', '=', '0')
    ->where('id', '=', $catID);
  $aShopGroups = $oShopGroup->findAll(FALSE);
  if (!empty($aShopGroups)) return $aShopGroups[0];
  return false;
}

// Get offer from DB by offer name
// Получение поля offer из БД по имени товара
function offerGetDB($offerName = null)
{
  $oShopItems = Core_Entity::factory('Shop_Item');
  $oShopItems
    ->queryBuilder()
    ->where('deleted', '=', '0');
  if (!empty($offerName)) $oShopItems->queryBuilder()->where('name', '=', $offerName);
  $aShopItems = $oShopItems->findAll(FALSE);
  if (empty($aShopItems)) {
    return false;
  } else {
    return $aShopItems[0];
  }
}

// HOST CMS guid, check has db offer with this $guid or not
// Проверка guid товара в системе HOST CMS
function guidCheck($guid, $dbOffers)
{
  $guidFound = false;
  foreach ($dbOffers as $row) {
    if ($row["guid"] == $guid) $guidFound = true;
  }
  if ($guidFound) return guidCheck($guid, $dbOffers);
  else return $guid;
}
