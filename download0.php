<?php
//Set more memory for this page
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0); 
// Report simple running errors
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//set default timezone
date_default_timezone_set('America/Los_Angeles');
// Writing to document root, because cron job dont identify document root.
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__);

// including datbase connection file.
//include "db.php";
//including functionality to fetch seo informations
require_once 'functions.php';

//Create a new object for functionality
$obj = new functions();

// define configuration variables
// name of the server zip files with their directory structure

$server_file = array(
   array( 'filename'=>'expiring_service_no_adult_auctions.csv.zip', 'diretory_struct' => 'expiring_service_no_adult_auctions.csv' )
);
// local file location on own server, where the zip extract gonna reside, for reading purpose.
$extracted_file_location = $_SERVER['DOCUMENT_ROOT'].'/newextracts0';          
// define("extracted_file_location",$_SERVER['DOCUMENT_ROOT'].'/newextracts0');  

mail( "voung.sd@gmail.com", "Cron Job-Started", "Started" );

$log = "Cron Job log report <br>";

$log .= ".................................................................................................................<br>";
$log .= "Start time : " . date("m/d/Y-h:i:sa")."<br>";

function getFile($zip_files) {
	$extracted_file_location = $_SERVER['DOCUMENT_ROOT'].'/newextracts0';
    if (file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename'])) {
    	unlink($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename']);
    }
    if (file_exists($extracted_file_location.'/'.$zip_files['diretory_struct'])) {
    	unlink($extracted_file_location.'/'.$zip_files['diretory_struct']);
    }
    
    $fh = fopen($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename'], 'a');
    
    echo "Connecting FTP...<br>";
    $con = ftp_connect('ftp.godaddy.com');
    ftp_login($con, 'auctions', '');
    ftp_pasv($con, true);
    $ftp_option = ftp_set_option($con, FTP_TIMEOUT_SEC, 3600);
    
    $log .= "Set option for FTP : ".$ftp_option ."...<br>";
    
    if (file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename'])) {
        $size  = ftp_size($con, $zip_files['filename']);
        $log .= "Actual FTP file size : ".$size."...<br>";
        $size2 = filesize($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename']);
        @ftp_fget($con, $fh, $zip_files['filename'], FTP_BINARY, 0);
        fclose($fh);
        clearstatcache();
        $log .= "Local file size : ".filesize($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename'])."...<br>";
        if (filesize($_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename']) !== $size) {
            $log .= "Getting the file again...<br>";
            getFile($zip_files);
        }
    }
    else
        @ftp_fget($con, $fh, $zip_files['filename'], FTP_BINARY);

    ftp_close($con);
    
    mail( "voung.sd@gmail.com", "Cron Job-FTP", $log );
    //echo $log;
}

function Getfloat($str) {
  if(strstr($str, ",")) {
    $str = str_replace(".", "", $str);
    $str = str_replace(",", ".", $str);
  }
  if(preg_match("#([0-9\.]+)#", $str, $match)) {
    return floatval($match[0]);
  }
}
$count = 0;

foreach( $server_file as $key => $zip_files ) {
    
    getFile($zip_files);
    
    //Extracting the zip file.
    $log .= "extracting file started"."...<br>";
    $zip = new ZipArchive();
	$extracted_file_location = $_SERVER['DOCUMENT_ROOT'].'/newextracts0';
    if( $zip->open( $_SERVER['DOCUMENT_ROOT'].'/'.$zip_files['filename'] ) === TRUE) {
      $log .= "extracting to ".$extracted_file_location."...<br>";
      $zip->extractTo( $extracted_file_location );
      $zip->close();
    } else {
      $log .= "There was a problem extracting the file. Please contact server administrator if the ZipArchive is enabled...<br>";
      mail( "voung.sd@gmail.com", "Cron Job-FTP", $log );
      die('failed');
    }
     
    //Parsing the CSV file and writing to table
    $log .= "Parsing the CSV file and writing to table...<br>";
    $file = fopen( $extracted_file_location.'/'.$zip_files['diretory_struct'], "r" );
	//initializing insert query_string
	$insert_query = "INSERT INTO tdnam_all_listings(domain_name,item_id,action_type,time_left,price,bids,domain_age,traffic,valuation_price,archive_num,link_domain,demoz,alexa,fb_likes) VALUES ";
	
	$log .= "Reading Start time : " . date("m/d/Y-h:i:sa")."<br>";
	
	$insert_data = array(); 
	
    $x = 0;
    
    //Deleting all the records from table.
    $host="localhost";
    $user="dave";
    $pass="DAV137idl!";
    $dbName="php_test";
    $link=mysql_connect($host, $user, $pass);
    mysql_select_db($dbName,$link) or die("database connection fail");
    mysql_query( "DELETE FROM tdnam_all_listings;" ) or die( mysql_error() );
   	
    do{
      
      if($x == 25000){ //Previous value was 25000
        break;
      }
      
      $data = fgetcsv($file);
      // Assuming first record will be titles in the CSV document.
      if( $i == 0 ) {                          // Skipping the first record from CSV file
        $i++;
        continue;
      }

      // Validating amount
      $amount = Getfloat($data[4]);
      if( $amount && $amount > 500 ) {
        $log .= "Amount greater than $500 <br>";
        continue;
      }
      
      // validating newer records
      
      // should not be older than yesterday
      $record_date = date("d-m-Y",strtotime($data[3]));
      
      if( strtotime($record_date) < strtotime(date("d-m-Y")) || strtotime($record_date) > strtotime(date("d-m-Y",strtotime("+2 day"))) ) {
          $log = "Time mismatched : ".$record_date.", older than ".date("d-m-Y")."<br>";
		  $log .= "Compared dates in ms : Record Date:".strtotime($record_date).", Current Time:".strtotime(date("d-m-Y")).", Third Day:".strtotime(date("d-m-Y",strtotime("+2 day")))."<br>";
          $log .="<br>Current: ".date("d-m-Y H:i:s");
		  $log .="<br>Third: ".date("d-m-Y",strtotime("+2 day"));
		  $log .="<br>Record:".$record_date;
		  continue;
      }

      // Validating if not .com, only .com allowed
      preg_match('/com/i', strtolower($data[0]), $matches);
      if( count($matches) < 1 ) {
        $log .= "excluded : ". $data[0].", because its a non .com domain <br>";
        continue;
      }
      
      if(strtolower($data[2])!="buynow") {
      	if(strtolower($data[2])!="bid" ) {
	      	$log .= "Excluded as the type is other than BuyNow or Bid";
	      	continue;
      	}
      }

	//Fetch data by domain name_04052014
	$domain_name = strtolower($data[0]);
	$backlinks = 0;//$obj->get_total_backlinks($domain_name);
	$archive_no = $obj->get_archive_number($domain_name);
	$dimoz = 0;//$obj->get_total_dimoz($domain_name);
	$rank = $obj->get_rank($domain_name);
	$fb_count = $obj->get_facebook_count($domain_name);	
	
	$log .= "Archive No: ".$archive_no." <br>";
	$log .= "Rank: ".$rank." <br>";
	$log .= "FB Count: ".$fb_count." <br>";
	
      /*if (!mysql_ping($link)) {
       echo 'Lost connection, exiting after query #1';
       exit;
      }*/
	  //preparing values for insert query	
	 /*$insert_query.="('".$data[0]."','".$data[1]."','".$data[2]."','".$data[3]."','".$data[4]."','".$data[5]."','".$data[6]."','".$data[7]."','".$data[8]."','".$archive_no."','".$backlinks."','".$dimoz."','".$rank."','".$fb_count."'),"; */
	 
        $host="localhost";
   	$user="dave";
   	$pass="DAV137idl!";
   	$dbName="php_test";
   	$link=mysql_connect($host, $user, $pass);
   	mysql_select_db($dbName,$link) or die("database connection fail");
   
	mysql_query( "INSERT INTO tdnam_all_listings(domain_name,item_id,action_type,time_left,price,bids,domain_age,traffic,valuation_price,archive_num,link_domain,demoz,alexa,fb_likes)      VALUES('".$data[0]."','".$data[1]."','".$data[2]."','".$data[3]."','".$data[4]."','".$data[5]."','".$data[6]."','".$data[7]."','".$data[8]."','".$archive_no."','".$backlinks."','".$dimoz."','".$rank."','".$fb_count."')" ) or die( mysql_error() );
	  
	/*$insert_data[$count] = "('".$data[0]."','".$data[1]."','".$data[2]."','".$data[3]."','".$data[4]."','".$data[5]."','".$data[6]."','".$data[7]."','".$data[8]."','".$archive_no."','".$backlinks."','".$dimoz."','".$rank."','".$fb_count."')";*/
	
	$x++;
	$count++;
	
    }while( !feof($file) );

    fclose($file);
    
    echo "Reading End time : " . date("m/d/Y-h:i:sa")."<br>";
    
    //Trimming the last comma
    /*$insert_query = rtrim($insert_query,',');
    //echo "<br>INSERT QUERY : ".$insert_query."<br>";
    // Inserting data to table.
    mysql_query($insert_query) or die(mysql_error());*/
	
   /*$host="localhost";
   $user="dave";
   $pass="DAV137idl!";
   $dbName="php_test";
   $link=mysql_connect($host, $user, $pass);
   mysql_select_db($dbName,$link) or die("database connection fail");
    
   $log .= "Data to be inserted : ".$count."<br>"; 
    
   foreach ($insert_data as $item) {
    	$queryStr = $insert_query.$item;
    	//echo "Data : ".$queryStr."<br>";   
	mysql_query($queryStr) or die(mysql_error());
    }*/
    
    $log .= "Successfully imported the data in table...<br>";
    
    //After the operation delete the exiting files (i.e. Zip and CSV)
    /*if (file_exists($zip_files['filename'])) {
    	$log .= "Deleting the file : ".$zip_files['filename']." : ".unlink($zip_files['filename'])." ...<br>";
    }*/
    $extracted_file_location = $_SERVER['DOCUMENT_ROOT'].'/newextracts0';
    if (file_exists($extracted_file_location.'/'.$zip_files['diretory_struct'])) {
    	unlink($extracted_file_location.'/'.$zip_files['diretory_struct']);
    	$log .= "Deleting the file : ".$extracted_file_location.'/'.$zip_files['diretory_struct']." ...<br>";
    }
    
    $log .= "......................................End of operation for one file......................................................<br>";
}

$log .= "End time : " . date("m/d/Y-h:i:sa")."<br>";
mail( "voung.sd@gmail.com", "Cron Job - Ended", $log );
//echo $log;
die("Done");

?>