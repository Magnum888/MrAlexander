<?php ignore_user_abort(true); error_reporting(0);

// Описание на https://blog.biscripter.ru/landing-forms/
//https://api.telegram.org/bot454528987:AAEWx30GKclkJroSe412WwwYLcFZqeQCoks/sendMessage?chat_id=-274016090&text=hello!
/***************************************************************************
 *                              Настройки                                  *
 ***************************************************************************/

const NOTIFICATIONS_EMAIL = "kovel.web@yahoo.com";
const TELEGRAM_TOKEN = "454528987:AAEWx30GKclkJroSe412WwwYLcFZqeQCoks";
const TELEGRAM_CHAT_ID = "-274016090";


/***************************************************************************
 *                                Логика                                   *
 ***************************************************************************/

// Получаем данные из формы
$input = getInput();

// Если превышен суммарный максимальный размер всех полей формы (вместе с файлами), показываем страницу с ошибкой
// и просим заполнить форму еще раз
if (empty($input["text"]) && empty($input["files"]) && (int)$_SERVER['CONTENT_LENGTH'] > 512 * 1024) {
    showPostExceededError();
}

// Разбираем полученные файлы
$files = getFiles($input);

// Если есть файлы, превышающие максимальный разрешенный размер - показываем страницу с ошибкой
// и просим заполнить форму еще раз
$bigFiles = getBigFiles($files);
if (count($bigFiles) != 0) {
    showBigFilesError($input, $bigFiles);
}

// Если есть ошибки (серверные) при загрузке файлов, пишем об этом в логи
$errorFiles = getErrorFiles($files);
if (count($errorFiles) != 0) {
    foreach ($errorFiles as $file) {
        error_log("Fail to upload file {$file["name"]}. Error code: {$file["error"]}", 0);
    }
}

// Получаем список успешно загруженных файлов
$goodFiles = getGoodFiles($files);

// Создаем новый ID для заявки. Это поможет идентифицировать апселлы
$leadId = time();

// Если указан email для уведомлений - отправляем письмо
if (defined("NOTIFICATIONS_EMAIL") && NOTIFICATIONS_EMAIL != "") {
    $emailSent = sendEmail($input, $leadId, $errorFiles, $goodFiles);
}

// Если указаны данные для Telegram бота - шлем лид в Telegram
if (defined("TELEGRAM_TOKEN") && TELEGRAM_TOKEN != "" && defined("TELEGRAM_CHAT_ID") && TELEGRAM_CHAT_ID != "") {
    $telegramSent = sendTelegram($input, $leadId, $errorFiles, $goodFiles);
}

// Если заявка не отправилась ни на Email, ни в Telegram - показываем посетителю ошибку отправки формы
if (!$emailSent && !$telegramSent) {
    showFormError();
}

// Если указан URL редиректа - делаем редирект
if ($input["text"]["redirect"]) {
    redirect($input, $leadId);
}

// Если редирект не настроен - показываем стандартную страницу благодарности
showDefaultThankyouPage($input);


/***************************************************************************
 *                               Функции                                   *
 ***************************************************************************/

function httpRequest($url, $method = "GET", $headers = [], $data = NULL)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, "PHP API Client");
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($method == "POST") {
        $headers [] = 'Content-Type: multipart/form-data';
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($curl);
    if ($result === false) {
        throw new Exception("httpRequest failed: " . curl_error($curl));
    }
    $responseBody = json_decode($result, true);
    $responseStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($responseStatus != 200) {
        throw new Exception("httpRequest failed: " . print_r($responseBody));
    }
    return $responseBody;
}

function getFileMaxSize($input)
{
    $max_size = -1;
    if ($max_size < 0) {
        $post_max_size = getPostMaxSize();
        if ($post_max_size > 0) {
            $max_size = $post_max_size;
        }
        $upload_max = parseSize(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
        if ((int)$input["text"]["MAX_FILE_SIZE"] > 0 && (int)$input["text"]["MAX_FILE_SIZE"] < $max_size) {
            $max_size = (int)$input["text"]["MAX_FILE_SIZE"];
        }
    }
    return $max_size;
}

function getPostMaxSize()
{
    $max_size = -1;
    if ($max_size < 0) {
        $post_max_size = parseSize(ini_get('post_max_size'));
        if ($post_max_size > 0) {
            $max_size = $post_max_size;
        }
    }
    return $max_size;
}

function parseSize($size)
{
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

function render($html)
{
    echo "
      <html lang='ru'>
      <head>
        <meta charset='UTF-8'>
        <meta http-equiv='X-UA-Compatible' content='IE=edge'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <link rel='shortcut icon' href='https://freelandings.ru/assets/favicons/favicon.ico'>
        <title>Спасибо за заказ!</title>
        <style>
          body{
            margin: 0;
            background: #43cea2;
            background: -webkit-linear-gradient(to right, #185a9d, #43cea2);
            background: linear-gradient(to right, #185a9d, #43cea2);
            height: 100vh;
            padding: 2rem;
            color: white;
            font-family: 'Open Sans', sans-serif;
            text-align: center;
            box-sizing: border-box;
            text-shadow: 0 1px 2px rgba(0,0,0,.1);
          }
          .content {
            position: absolute;
            width: 80%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            color: rgba(0, 0, 0, .75);
            -webkit-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            -moz-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            -webkit-border-radius: 4px;
            -moz-border-radius: 4px;
            border-radius: 4px;
            padding: 1rem;
            text-shadow: none;
          }
          @media screen and (min-width: 900px) {
            .content {
              max-width: 600px;
              padding: 2rem;
            }
          }
          .content ul {
            margin: 2rem 0;
            text-align: left;
          }
          .content button {
            padding: .5rem 1rem;
            -webkit-border-radius: 2px;
            -moz-border-radius: 2px;
            border-radius: 2px;
            color: white;
            background-color: #2BC0E4;
            border: none;
            -webkit-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            -moz-box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            box-shadow: 0 0 8px 2px rgba(0,0,0,.2);
            text-shadow: 0 1px 2px rgba(0,0,0,.1);
          }
          .content button:hover {
            cursor: pointer;
            -webkit-transform: translateY(-1px);
            -moz-transform: translateY(-1px);
            -ms-transform: translateY(-1px);
            -o-transform: translateY(-1px);
            transform: translateY(-1px);
          }
        </style>
      </head>
      <body>
        <div class='content'>
          $html
          <button onclick='history.go(-1);'>Вернуться</button>
        </div>
      </body>
      </html>
    ";
    exit(0);
}

function getInput()
{
    $input = [
        "text" => $_SERVER["REQUEST_METHOD"] == "POST" ? $_POST : $_GET,
        "files" => $_FILES
    ];
    return $input;
}

function getFiles($input)
{
    $files = [];
    foreach ($input["files"] as $entry) {
        if (!is_array($entry["name"])) {
            if (empty($entry["name"])) continue;
            $entry["name"] = basename($entry["name"]);
            $files [] = $entry;
            continue;
        }
        for ($i = 0; $i < count($entry["name"]); $i++) {
            $file = [];
            foreach ($entry as $name => $values) {
                $file[$name] = $values[$i];
            }
            if (empty($file["name"])) continue;
            $file["name"] = basename($file["name"]);
            $files [] = $file;
        }
    }
    return $files;
}

function getBigFiles($files)
{
    $bigFiles = [];
    foreach ($files as $file) {
        if ($file["error"] == UPLOAD_ERR_INI_SIZE || $file["error"] == UPLOAD_ERR_FORM_SIZE) {
            $bigFiles [] = $file;
        }
    }
    return $bigFiles;
}

function getErrorFiles($files)
{
    $errorFiles = [];
    foreach ($files as $file) {
        if ($file["error"] > UPLOAD_ERR_FORM_SIZE) {
            $errorFiles [] = $file;
        }
    }
    return $errorFiles;
}

function getGoodFiles($files)
{
    $goodFiles = [];
    foreach ($files as $file) {
        if ($file["error"] == UPLOAD_ERR_OK) {
            $goodFiles [] = $file;
        }
    }
    return $goodFiles;
}

function translateUploadError($errorCode)
{
    $errorMessage = "";
    switch ($errorCode) {
        case UPLOAD_ERR_PARTIAL:
            $errorMessage = "Завантаження файл був отриманий тільки частково";
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage = "Файл не був завантажений";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMessage = "Відсутня тимчасова папка";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMessage = "Не вдалося записати файл на диск";
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMessage = "PHP-розширення зупинило завантаження файлу";
            break;
    }
    return $errorMessage;
}

function getSiteName()
{
    $siteName = $_SERVER["HTTP_REFERER"]
        ? preg_split("/[?\/]/", $_SERVER["HTTP_REFERER"], -1, PREG_SPLIT_NO_EMPTY)[1]
        : "";
    return $siteName;
}

function sendEmail($input, $leadId, $errorFiles, $goodFiles)
{
    $ok = true;
    $siteName = getSiteName();
    $subject = $input["text"]["leadId"]
        ? "Нове повідомлення {$input["text"]["leadId"]} з сайту $siteName"
        : "Нове повідомлення $leadId з сайту $siteName";
    $uid = md5(uniqid(time()));
    $body = "--$uid\r\n";
    $body .= "Content-type:text/html; charset=utf-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= "<html><body>";
    $body .= $input["text"]["leadId"]
        ? "<p>Апселл до замовлення {$input["text"]["leadId"]}:</p>"
        : "<p>Нове замовлення:</p>";
    $body .= "<ul><li>ID: ";
    $body .= $input["text"]["leadId"]
        ? $input["text"]["leadId"]
        : $leadId . "</li>";
    foreach ($input["text"] as $name => $value) {
        $name != "redirect" && $name != "leadId" && $name != "MAX_FILE_SIZE" && $body .= "<li>$name: $value</li>";
    }
    $body .= "
      <li>IP: {$_SERVER["REMOTE_ADDR"]}</li>
      <li>URL: {$_SERVER["HTTP_REFERER"]}</li>
      <li>User Agent: {$_SERVER["HTTP_USER_AGENT"]}</li>
      </ul>
    ";
    if (count($errorFiles) != 0) {
        $body .= "<p>Помилки загрузки файлів:</p><ul>";
        foreach ($errorFiles as $file) {
            $body .= "<li>{$file["name"]}: " . translateUploadError($file["error"]) . "</li>";
        }
        $body .= "</ul>";
    }
    $body .= "</body></html>\r\n\r\n";
    foreach ($goodFiles as $file) {
        $body .= "--$uid\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"{$file["name"]}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$file["name"]}\"\r\n\r\n";
        $content = chunk_split(base64_encode(file_get_contents($file["tmp_name"])));
        $body .= "$content\r\n\r\n";
    }
    $body .= "--$uid--";
    try {
        $sent = mail(NOTIFICATIONS_EMAIL, $subject, $body, implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-type: multipart/mixed; boundary=\"$uid\""
        ]));
        if (!$sent) {
            throw new Exception("Email not accepted by MTA");
        }
    } catch (Exception $e) {
        error_log("Fail to send notification email: $e", 0);
        $ok = false;
    }
    return $ok;
}

function sendTelegram($input, $leadId, $errorFiles, $goodFiles)
{
    $ok = true;
    $siteName = getSiteName();
    $message = $input["text"]["leadId"]
        ? "Апселл до замовлення {$input["text"]["leadId"]} з сайту $siteName\n\n"
        : "Нове замовлення на сайті $siteName\n\nID: $leadId\n";
    foreach ($input["text"] as $name => $value) {
        $name != "redirect" && $name != "leadId" && $name != "MAX_FILE_SIZE" && $message .= "$name: $value\n";
    }
    $message .= "IP: {$_SERVER["REMOTE_ADDR"]}\nURL: {$_SERVER["HTTP_REFERER"]}\nUser Agent: {$_SERVER["HTTP_USER_AGENT"]}\n";
    if (count($errorFiles) != 0) {
        $message .= "\nПомилки загрузки файлів:\n";
        foreach ($errorFiles as $file) {
            $message .= "{$file["name"]}: " . translateUploadError($file["error"]) . "\n";
        }
    }
    $message = urlencode($message);
    try {
        httpRequest("https://api.telegram.org/bot"
            . TELEGRAM_TOKEN
            . "/sendMessage?chat_id="
            . TELEGRAM_CHAT_ID
            . "&text={$message}");
    } catch (Exception $e) {
        error_log("Fail to send notification to telegram: $e", 0);
        $ok = false;
    }
    $leadId = $input["text"]["leadId"] ? $input["text"]["leadId"] : $leadId;
    foreach ($goodFiles as $file) {
        try {
            httpRequest("https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendDocument", "POST", [], [
                "chat_id" => TELEGRAM_CHAT_ID,
                "document" => curl_file_create($file["tmp_name"], "", $file["name"]),
                "caption" => "Файл до замовлення $leadId"
            ]);
        } catch (Exception $e) {
            error_log("Fail to send file to telegram: $e", 0);
            $ok = false;
        }
    }
    return $ok;
}

function redirect($input, $leadId)
{
    $redirectUrlSplitted = explode("?", $input["text"]["redirect"], 2);
    $redirectUrl = "{$redirectUrlSplitted[0]}?leadId=";
    $redirectUrl .= $input["text"]["leadId"] ?: $leadId;
    foreach ($input["text"] as $name => $value) {
        $name != "redirect" && $name != "leadId" && $redirectUrl .= "&$name=$value";
    }
    if (count($redirectUrlSplitted) > 1) $redirectUrl .= "&{$redirectUrlSplitted[1]}";
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(['redirect' => $redirectUrl]);
        exit(0);
    }
    header("Location: $redirectUrl");
    exit(0);
}

function showDefaultThankyouPage($input)
{
    if ($input["text"]["leadId"]) {
        $html = "<h3>Повідомлення відправлено успішно</h3>"
            . "<p>Ми зв'яжемося з вами протягом 15-30 хвилин для уточнення інформації. "
            . "Будь ласка, переконайтеся, що ви все ввели вірно</p><ul>";
        foreach ($input["text"] as $name => $value) {
            if ($name != "redirect" && $name != "leadId" && $name != "MAX_FILE_SIZE") $html .= "<li>$name: $value</li>";
        }
        $html .= "</ul>";
    } else {
        $html = "<h3>Ваша заявка прийнята</h3>"
            . "<p>Ми зв'яжемося з вами протягом 15-30 хвилин для уточнення інформації."
            . "Будь ласка, переконайтеся, що ваш телефон включений і перевірте введені дані</p><ul>";
        foreach ($input["text"] as $name => $value) {
            if ($name != "redirect" && $name != "MAX_FILE_SIZE") $html .= "<li>$name: $value</li>";
        }
        $html .= "</ul><p>Якщо ви помилилися, поверніться до форми і заповніть форму ще раз</p>";
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(['html' => $html]);
        exit(0);
    }
    render($html);
}

function showPostExceededError()
{
    $html = "<h3>Помилка відпраки форми</h3>"
        . "<p>Максимальний сумарний розмір файлів не повинен перевищувати "
        . round(getPostMaxSize() / 1024 / 1024, 2) . " МБ</p>"
        . "<p>Поверніться на головну сторінку і заповніть форму ще раз</p>";
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header("Content-Type: application/json; charset=utf-8");
        header("HTTP/1.0 400 Bad Request");
        echo json_encode(['html' => $html]);
        exit(0);
    }
    render($html);
}

function showBigFilesError($input, $bigFiles)
{
    $html = "<h3>Помилка відпраки форми</h3>"
        . "<p>Наступні файли не завантажені через занадто великого розміру</p><ul>";
    foreach ($bigFiles as $file) {
        $html .= "<li>{$file["name"]}</li>";
    }
    $html .= "<p></ul>Максимальний розмір файлу - "
        . round(getFileMaxSize($input) / 1024 / 1024, 2) . " МБ</p>";
    $html .= "<p>Поверніться на головну сторінку і заповніть форму ще раз</p>";
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header("Content-Type: application/json; charset=utf-8");
        header("HTTP/1.0 400 Bad Request");
        echo json_encode(['html' => $html]);
        exit(0);
    }
    render($html);
}

function showFormError()
{
    $html = "<h3>Помилка відправки форми</h3>"
        . "<p>Спробуйте відправити піздніше</p>";
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header("Content-Type: application/json; charset=utf-8");
        header("HTTP/1.0 400 Bad Request");
        echo json_encode(['html' => $html]);
        exit(0);
    }
    render($html);
}