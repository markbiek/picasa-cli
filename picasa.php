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

function replaceEmptyWithSpace(&$arr, $key) {
    $arr[$key] = isset($arr[$key]) ? $arr[$key] : '';
}

function createAlbum($opts) {
    if(!isset($opts['album-access'])) {
        $opts['album-access'] = 'public';
    }
    replaceEmptyWithSpace($opts, 'album-desc');
    replaceEmptyWithSpace($opts, 'album-location');

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
    //echo file_get_contents(FEED_URL);

    return getAlbumIDs($ret);
}

function albumList($opts = '') {
    if(!is_array($opts)) {
        $opts = array(
            'auth-header'=> AUTH_HEADER,
            'feed-url'=> FEED_URL,
        );
    }

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

function albumIDByName($find, $albums = '') {
    if(!is_array($albums)) {
        $albums = albumList();
    } 

    foreach($albums as $name=>$id) {
        if($find == $name) {
            return $id;
        }
    }
}

function uploadImage($opts) {
    file_exists($opts['image']) || die("Could not locate file {$opts['image']}\n");

    if(!isset($opts['image-title'])) {
        $opts['image-title'] = $opts['image'];
    }
    if(!isset($opts['image-desc'])) {
        $opts['image-desc'] = '';
    }

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
    /*$header = array('GData-Version:  2', AUTH_HEADER, 'Content-Type: image/jpeg', 'Content-Length: ' . $fileSize, 'Slug: cute_baby_kitten.jpg');
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

function syncPath($path) {
    $files = array();
    $dir = array();

    $h = opendir($path);
    while( ($e = readdir($h)) !== false ) {
        if($e != '.' && $e != '..') {
            if(is_dir("$path/$e")) {
                syncPath("$path/$e");
            }else {
                if( in_array(strtolower(pathinfo($e, PATHINFO_EXTENSION)),array('jpg','jpeg','gif','tiff','png')) ) {
                    $files[] = trim($e, '/');
                }
            }
        }
    }
    closedir($h);

    $key = trim(str_replace('--', '-', str_replace('/', '-', str_replace('.', '', $path))), '-');
    $dir[$key] = array('path'=>trim($path, '/'), 'files'=>$files);

    foreach($dir as $key=>$data) {
        createAlbum(array(
            'auth-header'=> AUTH_HEADER,
            'feed-url'=> FEED_URL,
            'album-title'=> $key,
        ));
        foreach($data['files'] as $img) {
            $fullPath = str_replace('//', '/', $data['path'] . '/' . $img);
            uploadImage(array(
                'auth-header'=> AUTH_HEADER,
                'album-id'=> albumIDByName($key),
                'image'=> $fullPath
            ));
        }
    }
}

function loadConfig($file='.picasa.conf') {
    //First look for the file in the default location
    //(A user specified location or the current directory)
    if(!file_exists($file)) {
        $pwuid = posix_getpwuid(getmyuid());
        //If we can't find it, check the user's home directory
        if(!file_exists($pwuid['dir'] . '/' . $file)) {
            die("The config file $file could not be found.\n");
        }
    }

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
 --upload-album=ALBUM-NAME      The name of the album where uploaded images are placed 
 --sync=DIR                     Given a path, upload all images, creating albums for each directory. (Not a true "sync")
 --username=USERNAME            The username to authenticate as. (Can also be placed in .picasa.conf)
 --password=PASSWORD            The password to authenticate with. (Can also be placed in .picasa.conf)
EOD;

    print $helpText . "\n";
}

/************************************************************/

$args = getArgs();

if(isset($args['help']) || count($args) <= 0) {
    printHelp();
    die();
}

$config = loadConfig();
define('FEED_URL', "https://picasaweb.google.com/data/feed/api/user/default"); //"default" uses the userId of the authenticating user
define('AUTH_HEADER', auth($config['username'], $config['password']));
$albumIDs = array();

if(isset($args['username'])) {
    $config['username'] = $args['username'];
}
if(isset($args['password'])) {
    $config['password'] = $args['password'];
}

if(isset($args['sync'])) {
    syncPath($args['sync']);
    die(); //Don't allow any other operations after this
}

if(isset($args['create-album'])) {
    $args['album-access'] = !isset($args['album-access']) ? '' : $args['album-access'];
    $args['album-desc'] = !isset($args['album-desc']) ? '' : $args['album-desc'];
    $args['album-location'] = !isset($args['album-location']) ? '' : $args['album-location'];

    $albumIDs = createAlbum(array(
        'auth-header'=> AUTH_HEADER,
        'feed-url'=> FEED_URL,
        'album-title'=> $args['create-album'],
        'album-desc'=> $args['album-desc'],
        'album-location'=> $args['album-location'],
        'album-access'=> $args['album-access']
    ));
}

if(isset($args['upload-image'])) {
    if(isset($args['upload-album'])) {
        $albumID = albumIDByName($args['upload-album']);
    }else {
        if(count($albumIDs) == 0 ) {
            die("Please specify an album to upload to.");
        }
        if(count($albumIDs) > 1 ) {
            die("Please specify only one album to upload to.");
        }
        $albumID = reset($albumIDs);
    }

    $files = glob($args['upload-image']);
    foreach($files as $file) {
        uploadImage(array(
            'auth-header'=> AUTH_HEADER,
            'album-id'=> $albumID,
            'image'=> $file
        ));
    }
}
?>
