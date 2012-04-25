#!/usr/bin/php
<?php
function auth($user, $pass) {
    $ch = curl_init();  

    curl_setopt($ch, CURLOPT_URL, "https://www.google.com/accounts/ClientLogin");  
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  

    $data = array('accountType' => 'GOOGLE',  
    'Email' => $user,  
    'Passwd' => $pass,  
    'source'=>'PHP Picasa Utility',  
    'service'=>'lh2');  

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);  
    curl_setopt($ch, CURLOPT_POST, true);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  

    $gvals = array();

    $ret = explode("\n",curl_exec($ch));  
    curl_close($ch);

    foreach($ret as $item) {
        $flds = explode("=", $item);

        if(count($flds) > 1) {
            $gvals[$flds[0]] = $flds[1]; 
        }
    }

    //echo '<pre>' . print_r($gvals,true) . '</pre>';

    $authHeader = 'Authorization:  GoogleLogin auth="' . $gvals['Auth'] . '"';

    return $authHeader;
}

function getAlbumIDs($rawXML) {
    if(!function_exists('parseEntry')) {
        function parseEntry($child) {
            $fields = explode('/', (string)$child->id);
            return array((string)$child->title, $fields[count($fields)-1]);
        }
    }

    $xml = new SimpleXMLElement($rawXML);

    $albums = array();
    if($xml->getName() == 'entry') {
        //We have a single entry element (like the return from createAlbum)
        list($title, $id) = parseEntry($xml);
        $albums[$title] = $id;
    }else {
        //We have an unknown number of albums (like the return from albumList
        foreach($xml->children() as $child) {
            if($child->getName() == 'entry') {
                list($title, $id) = parseEntry($child);
                $albums[$title] = $id;
            }
        }
    }

    return $albums;
}

function createAlbum($opts) {
    $rawXml = "<entry xmlns='http://www.w3.org/2005/Atom'
                    xmlns:media='http://search.yahoo.com/mrss/'
                    xmlns:gphoto='http://schemas.google.com/photos/2007'>
                  <title type='text'>{$opts['album-title']}</title>
                  <summary type='text'>{$opts['album-desc']}</summary>
                  <gphoto:location>{$opts['album-location']}</gphoto:location>
                  <gphoto:access>{$opts['album-access']}</gphoto:access>
                  <gphoto:timestamp>1152255600000</gphoto:timestamp>
                  <category scheme='http://schemas.google.com/g/2005#kind'
                    term='http://schemas.google.com/photos/2007#album'></category>
                </entry>";

    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL, $opts['feed-url']);  
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  

    $options = array(
                CURLOPT_SSL_VERIFYPEER=> false,
                CURLOPT_POST=> true,
                CURLOPT_RETURNTRANSFER=> true,
                CURLOPT_HEADER=> false,
                CURLOPT_FOLLOWLOCATION=> true,
                CURLOPT_POSTFIELDS=> $rawXml,
                CURLOPT_HTTPHEADER=> array('GData-Version:  2', $opts['auth-header'], 'Content-Type:  application/atom+xml')
            );
    curl_setopt_array($ch, $options);

    $ret = curl_exec($ch);
    curl_close($ch);

    //header('Content-type: text/plain');
    //echo $ret;

    //header('Content-type: text/xml');
    //echo file_get_contents($feedUrl);

    return getAlbumIDs($ret);
}

function albumList($opts) {
    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL, $opts['feed-url']);  
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  

    $options = array(
                CURLOPT_SSL_VERIFYPEER=> false,
                CURLOPT_RETURNTRANSFER=> true,
                CURLOPT_HEADER=> false,
                CURLOPT_FOLLOWLOCATION=> true,
                CURLOPT_HTTPHEADER=> array('GData-Version:  2', $opts['auth-header'], 'Content-Type:  application/atom+xml')
            );
    curl_setopt_array($ch, $options);

    $ret = curl_exec($ch);
    curl_close($ch);

    //header('Content-type: text/plain');
    //echo $ret;
    
    return getAlbumIDs($ret);
}

function uploadImage($opts) {
    if(!isset($opts['image-title'])) {
        $opts['image-title'] = $opts['image'];
    }
    if(!isset($opts['image-desc'])) {
        $opts['image-desc'] = '';
    }

    print_r($opts);

    $rawImgXml = '<entry xmlns="http://www.w3.org/2005/Atom">
                  <title>' . $opts['image-title'] . '</title>
                  <summary>' . $opts['image-desc'] . '</summary>
                  <category scheme="http://schemas.google.com/g/2005#kind"
                    term="http://schemas.google.com/photos/2007#photo"/>
                </entry>';


    $fileSize = filesize($opts['image']);
    $fh = fopen($opts['image'], 'rb');
    $imgData = fread($fh, $fileSize);
    fclose($fh);

    $data = "";
    $data .= "\nMedia multipart posting\n";
    $data .= "--P4CpLdIHZpYqNn7\n";
    $data .= "Content-Type: application/atom+xml\n\n";
    $data .= $rawImgXml . "\n";
    $data .= "--P4CpLdIHZpYqNn7\n";
    $data .= "Content-Type: image/jpeg\n\n";
    $data .= $imgData . "\n";
    $data .= "--P4CpLdIHZpYqNn7--";

    $header = array('GData-Version:  2', $opts['auth-header'], 'Content-Type: multipart/related; boundary=P4CpLdIHZpYqNn7;', 'Content-Length: ' . strlen($data), 'MIME-version: 1.0');

    //This works for uploading an image WITHOUT metadata
    /*$header = array('GData-Version:  2', $authHeader, 'Content-Type: image/jpeg', 'Content-Length: ' . $fileSize, 'Slug: cute_baby_kitten.jpg');
    $data = $imgData;*/

    $ret = "";
    $albumUrl = "https://picasaweb.google.com/data/feed/api/user/default/albumid/{$opts['album-id']}";
    $ch  = curl_init($albumUrl);
    $options = array(
            CURLOPT_SSL_VERIFYPEER=> false,
            CURLOPT_POST=> true,
            CURLOPT_RETURNTRANSFER=> true,
            CURLOPT_HEADER=> true,
            CURLOPT_FOLLOWLOCATION=> true,
            CURLOPT_POSTFIELDS=> $data,
            CURLOPT_HTTPHEADER=> $header
        );
    curl_setopt_array($ch, $options);
    $ret = curl_exec($ch);
    curl_close($ch);

    //header('Content-type: text/plain');
    //echo "\n\nret=$ret\n\n";

    //echo "\n\n" . print_r($header,true);
    //echo "\n\n@\n$data";
}

function loadConfig($file='picasa.conf') {
    $config = array();
    $rawConfig = file_get_contents($file);
    $lines = explode("\n", $rawConfig);

    foreach($lines as $line) {
        $fields = explode("=", $line);
        if(count($fields) == 2) {
            $config[trim($fields[0])] = trim($fields[1]);
        }
    }

    return $config;
}

function getArgs() {
    global $argv;
    $args = array();

    foreach($argv as $arg) {
        $fields = explode('=', $arg);
        $fields[1] = !isset($fields[1]) ? '' : $fields[1];
        $args[trim($fields[0],'-')] = $fields[1];
    }
    return array_slice($args, 1);
}

function printHelp() {
    $helpText = <<<EOD
picasa command-line utility

Usage: picasa.php [OPTION]

Options
 --create-album=ALBUM-NAME      Creates a new Picasa album
 --album-desc=DESC              The description for a new album (Optional)
 --album-location=LOCATION      The location of the option (Optional)
 --private-album                Sets a newly created album to private. (Optional. Default visibility is public)
 --upload-image=FILE(s)         Uploads a file or files to the specified album. 
                                If --create-album is specified, photos are uploaded to the new album

 --upload-album=ALBUM-NAME      The name of the album to upload images to
EOD;

    print $helpText . "\n";
}

$args = getArgs();

if(isset($args['help']) || count($args) <= 0) {
    printHelp();
    die();
}

$config = loadConfig();
$feedUrl = "https://picasaweb.google.com/data/feed/api/user/default"; //"default" uses the userId of the authenticating user
$authHeader = auth($config['username'], $config['password']);

if(isset($args['create-album'])) {
    $args['album-access'] = !isset($args['album-access']) ? '' : $args['album-access'];
    $args['album-desc'] = !isset($args['album-desc']) ? '' : $args['album-desc'];
    $args['album-location'] = !isset($args['album-location']) ? '' : $args['album-location'];

    $albumIDs = createAlbum(array(
        'auth-header'=> $authHeader,
        'feed-url'=> $feedUrl,
        'album-title'=> $args['create-album'],
        'album-desc'=> $args['album-desc'],
        'album-location'=> $args['album-location'],
        'album-access'=> $args['album-access']
    ));

    print_r($albumIDs);
}

if(isset($args['upload-image'])) {
    $files = glob($args['upload-image']);
    print_r($files);
    /*uploadImage(array(
        'auth-header'=> $authHeader,
        'album-id'=> $albums['My Test Album'],
        'image'=> '/home/mark/websites/tmp.janustech.net/htdocs/picasa/cute_baby_kitten.jpg',
    ));*/
}

/*
$albums = albumList(array(
            'auth-header'=> $authHeader,
            'feed-url'=> $feedUrl,
        ));
print_r($albums);
 */

/*
uploadImage(array(
    'auth-header'=> $authHeader,
    'album-id'=> $albums['My Test Album'],
    'image'=> '/home/mark/websites/tmp.janustech.net/htdocs/picasa/cute_baby_kitten.jpg',
));
*/

/*
createAlbum(array(
    'auth-header'=> $authHeader,
    'feed-url'=> $feedUrl,
    'album-title'=> 'My Test Album',
    'album-desc'=> 'This is my test album',
    'album-access'=> 'public'
));
 */
?>
