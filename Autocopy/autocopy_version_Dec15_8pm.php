<?php

/**
 *  AUTOCOPY by Adam Borecki
 * 
 * 1. Variables and definitions
 * 
 * 2. Procedural code
 * 
 * 3. Functions
 * 
 */

 /** CHANGE LOG
  * 12/13 - replace $dest_folder with "label", and the date is always the date/time of the dump
  */

// about this script
$script_info = array(
    "name" => "autocopy",
    "author" => "Adam Borecki",
    "organization" => "recording.LA",
    "description" => "This script copies files from multiple SD cards (volumes) to HDDs or SSDs (volumes).",
    "version" => "0.0",
    "history_path" => "/Users"."/".exec("whoami")."bin/Autocopy/history/",
    "todo" => array(
        "allow destionation_path to be changed by user. will required some formatting and validation stuff. do that later not important enough now",

        "validate last transfer",
        "demo filesizes",
        "post reports to recording.LA database",
    ),
    /****
     * Changelog
     */
);
echo "--- $script_info[name] v$script_info[version] by $script_info[author], $script_info[organization]\n\n";
$config = array(
    // todo. put sleep times in here?
    'spare_bytes_buffer' => 200*1000*1000, // 200 * 1000 (KB) * 1000 (MB)
    'has_displayed_once' => false,
);
date_default_timezone_set("America/Los_Angeles");

define("PURPOSE_SRC",">");
define("PURPOSE_DEST","<");
define("PURPOSE_UNKNOWN","?");
define("PURPOSE_IGNORE","-");
$purposes = array(
    PURPOSE_IGNORE => "ignore",
    PURPOSE_SRC => "src",
    PURPOSE_DEST => "dest",
    PURPOSE_UNKNOWN => "unknown",
);



/*
 * 2. PROCEDURAL CODE
 * 
 */
$dir_volumes = "/Volumes";
if(false)
    $dir_volumes = "/Users/borecki/Desktop/DemoVolumes";
$volumes = get_volumes($dir_volumes);
$num_volumes = count(get_contents($dir_volumes));

echo "Look at the hardware.\n";
echo "Count the number of volumes (SD cards or SSDs etc.) that you expect to copy.\n";
echo "How many sources are connected to this computer?\n";
$expected_volumes = prompt_user_input();

$ready = false;
while(!$ready){
    $bytes_required = get_bytes_required($volumes);

    // todo: make better code. Calculation for $all_have_enough_space should not be part of the display function
    list($all_have_enough_space) = display_volumes($volumes, $purposes, $bytes_required);
    
    echo "What do you want to do? (type a number to edit that volume OR type 'ready' to proceed OR control + C to quit)\n";

    $user_input = prompt_user_input();

    if(is_numeric($user_input)){
        if(!isset($volumes[$user_input])){
            echo "Error: that volume number does not exist.\n";
            echo "Press ENTER to continue....";
            prompt_user_input();
            echo "\n";
        } else {
            // $volumes is passed by reference FYI
            edit_volume($volumes,$user_input,$purposes);
        }
    }
    else {
        switch($user_input){
            case "ready":
                if(!$all_have_enough_space){
                    echo "Error: You cannot type `ready` because one or more destination volumes do not have enough available space.\n\n";
                    echo "Press enter to continue;\n";
                    prompt_user_input();
                }else{
                    $ready = true;
                }
                break;
            default:
                echo "UNKNOWN INPUT: $user_input\n";
                echo "Enter any text to continue...";
                prompt_user_input();
                echo "\n\n";

        }
    }
    $check_num_volumes = count(get_contents($dir_volumes));
    if($check_num_volumes != $num_volumes){
        echo "FATAL ERROR!  The number of volumes has changed. Please restart this script.";
        echo "\n";
        echo "Remember to count the number of source SD cards and verify it!";
        echo "\n";
        echo "(Why did this happen? Adam hasn't programmed the script to be able to handle changes in the ID numbers. If the total number of volumes changes, the data is screwed up so you have to restart it.)";
        echo "\n";
        echo "\n";
        die();
    }
    // go back if !$ready
}
// end of first loop

$label = prompt_label();

// // find out what the dest_folder should be 
// $dest_folder = prompt_dest_folder();
// echo "\$dest_folder = $dest_folder;\n";
// echo "\t(If this was a mistake, you can press CONTROL + C to quit the script and start over from the very beginning.)\n";
// echo "\t(All files will be transfered to a new folder called $dest_folder within each of the destinations that you specify later.)\n";

// prepare the AGENDA
$agenda = prepare_agenda($volumes,$label,$purposes);
if(!$agenda){
    die("\nError: there was no agenda. Please check and make sure you have at least 1 source and at least 1 destination (although it's really best to have 2 destinations!).\n\n");
}

// confirm number of SD cards
confirm_num_sources($volumes,$expected_volumes);


// show them the agenda
display_agenda($agenda);

echo "Is the \$agenda correct?\n";
echo "Press ENTER to continue, or CONTROL + C to quit.\n";
prompt_user_input();

// save a copy into history
// confirm agenda and do the copying!

# TODO
#echo "Making entry for history...\n";
#$history = make_history($volumes,$agenda,$dest_folder);
#$history_path = save_history($history);
#echo "History saved to $history_path...\n";

$osa_e_flags = array();
$counter = 0;
foreach($agenda as $item_index => $item){
    list($from_index,$from_path) = $item['from'];
    list($to_index,$to_path) = $item['to'];

    
    if (!is_dir($to_path)) {
        echo "Creating path! mkdir($to_path,0777,true)\n";
        mkdir($to_path, 0777, true);
    }
    
    $osa_e_flags[] = get_copy_e_flags($from_path,$to_path,$counter);
    $counter++;
}
$cmd = "osascript ".implode("",$osa_e_flags);
echo "Sending the following AppleScript command (via Terminal)...\n\n";
echo $cmd;
echo "\n\n";
passthru($cmd);

echo "The commands have been sent to Finder.\n\n";

echo "$script_info[name] is finished.";

echo "\n";
echo "\t(Remember: $script_info[name] only sends the copy commands to the Finder (via AppleScript). To monitor the progress, look in Finder).";
echo "\n";
echo "\t($script_info[name] does not wait for data to transfer. It alse does NOT validate whether the data was copied successfully!)";

echo "\n\n\n";



  
/**
 * 3. FUNCTIONS
 */
function prompt_user_input(){
    $handle = fopen ("php://stdin","r");
    $user_input = fgets($handle);
    $user_input = trim($user_input);
    fclose($handle);
    return $user_input;
}
function format_dest_folder_from_timestamp($timestamp, $return_generic=false){
    global $script_info;
    $now = time();
    if($return_generic){
        return "YYMMDD"."_".$script_info['name']."-".date("gia",$now)."/";
    }
    else
        return date("ymd",$timestamp)."_".$script_info['name']."-".date("gia",$now)."/";
}

function get_contents($dir){
    $contents = scandir($dir);
    foreach($contents as $key => $value)
        if(substr($value,0,1)===".")
            unset($contents[$key]);
    sort($contents);
    return $contents;
}
function get_volumes($dir){
    $volumes = array();
    $contents = get_contents($dir);
    foreach($contents as $name){
        $absolute_path = "/Volumes/$name/";

        $free = disk_free_space($absolute_path);
        $total = disk_total_space($absolute_path);
        if($total == 0)
            $percent_used = "X";
        else
            $percent_used = 100*round(($total-$free)/$total,4);
        
        $free_dec = bytes_decimal($free);
        $free_bin = bytes_iec_binary($free);

        $volumes[] = array(
            "name"=>"$name",
            "absolute_path" => $absolute_path,
            "destination_path" => guess_destination_path_from_name($name),
            "purpose"=>guess_purpose_from_name($name),
            "purpose_is_guessed"=>true,
            "free" => $free,
            "free_dec" => $free_dec,
            "free_bin" => $free_bin,
            "total" => $total,
            "bytes_used" => $total - $free,
            "percent_used" => $percent_used,
        );
    }
    return $volumes;
}

function fix_length($string,$exact_length) {
    if( strlen($string) > $exact_length - 1 ){
        return substr($string,0,$exact_length - 1 )." ";
    } else {
        for($i = mb_strlen($string,"UTF-8");$i < $exact_length;$i++)
            $string .= " ";
        return $string;
    }
}
function display_volumes($volumes, $purposes,$bytes_required){
    global $script_info,$config;
    $all_have_enough_space = true;
    $totals = array();
    /*
    [4] => Array
        (
            [name] => MountedInstallDisc1
            [absolute_path] => /Volumes/MountedInstallDisc1
            [destination_path] => recording.LA-autocopy/
            [purpose] => ? (or > or < or -)
            [free] => 189091840
            [free_dec] => 189.1 MB
            [free_bin] => 180.3 MiB
            [total] => 1180663808
            [percent_used] => 83.98
        )
     */
    echo "Listing volumes...\n";
    if(!$config['has_displayed_once'])
        passthru("sleep 0.3");
    foreach($purposes as $purpose_icon => $purpose_label){
        $totals[$purpose_icon] = 0;
        echo "$purpose_icon $purpose_label\n";
        if(!$config['has_displayed_once'])
            passthru("sleep 0.57");
        else
            passthru("sleep 0.01");
        foreach($volumes as $index => $volume){
            if($purpose_icon == $volume['purpose']){
                echo "  ";
                echo $volume['purpose'];
                echo "  ";
                echo fix_length($index,3);
                echo fix_length($volume['name'],30);
                echo "avail.: ";
                echo fix_length($volume['free_dec'],12);
                echo fix_length($volume['free_bin'],12);
                echo "used ";
                echo $volume['percent_used']."%";
                echo "\n";
                $totals[$purpose_icon]++;
                if($volume['purpose']==PURPOSE_DEST){
                    echo "         to: ";
                    // $temp_dest_name = format_dest_folder_from_timestamp(0, true); // true displays generic YYMMDD_autocopy/
                    echo format_dest_full_path($volume, "YYMMDD_hhmm", "label");
                    //echo $volume['absolute_path'];
                    //echo $volume['destination_path'];
                    echo "\n";
                    $enough_space = volume_has_enough_space($volume,$bytes_required);
                    if(!$enough_space)
                        $all_have_enough_space = false;
                    echo "        ";
                    if($enough_space)
                        echo "√ Has enough free space ($bytes_required bytes required)";
                    else
                        echo "X DOES NOT HAVE ENOUGH FREE SPACE ($bytes_required bytes required)";
                    echo "\n";
                }
                if(!$config['has_displayed_once'])
                    passthru("sleep 0.1");
                else
                    passthru("sleep 0.01");
            }
        }
        if($totals[$purpose_icon] == 0 )
            echo "    (none)\n";
    }
    echo "\n";
    echo $totals[PURPOSE_SRC]." source(s). ".$totals[PURPOSE_DEST]." destination(s). ";
    echo "(".count($volumes)." total volume(s) - if this changes, $script_info[name] must restart)";
    echo "\n\n";

    $config['has_displayed_once'] = true;

    return array($all_have_enough_space);
}

// from http://php.net/manual/en/function.disk-total-space.php
function bytes_decimal($Bytes){
    $Type=array("", "KB", "MB", "GB", "TB","PB");
    $counter=0;
    while($Bytes>=1000) // 1024 for iec binary
     {
      $Bytes/=1000; // 1024 for iec binary
      $counter++;
    }
    $Bytes = round($Bytes,1);
    return("".$Bytes." ".$Type[$counter]."");
}

// from http://php.net/manual/en/function.disk-total-space.php
function bytes_iec_binary($bytes){
    $symbols = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
    $exp = floor(log($bytes)/log(1024));

    return sprintf('%.1f '.$symbols[$exp], ($bytes/pow(1024, floor($exp))));
}

function get_copy_e_flags($src,$dest,$unique_num){
    $lines = array(
        "property source$unique_num : POSIX file \"".($src)."\" as alias",
        "property destination$unique_num : POSIX file \"".($dest)."\" as alias",
        "tell application \"Finder\"",
            "ignoring application responses",
                "duplicate source$unique_num to destination$unique_num",
            "end ignoring",
        "end tell",
    );

    $e_flags = "";
    foreach($lines as $line)
        $e_flags .= " -e ".escapeshellarg($line);
    //$cmd = escapeshellcmd($cmd);
    return $e_flags;
}


function guess_purpose_from_name($name){
    // see purposes
    /**
     * - ignore
     * > source
     * < destination
     * ? unknown
     */
    if($name == "Macintosh HD"){
        return "-";
    }
    $sources = array(
        "F8_SD",
        "Untitled",
        "LUMIX",
        "H5_SD",
        "DiskHFS",
        "SSD",
    );
    foreach($sources as $source){
        if($name == $source)
            return PURPOSE_SRC;
        else if(preg_match("/$source \d?/",$name)){
            return PURPOSE_SRC;
        }
    }
    $destinations = array(
        "work",
        "wrk",
        "post",
        "backup"
    );
    $name_lowercase = strtolower($name);
    foreach($destinations as $destination){
        if( strpos($name_lowercase,$destination) !== false )
            return PURPOSE_DEST;
    }
    return "?";
}
function guess_destination_path_from_name($name){
    if($name == "Macintosh HD"){
        $username = exec('whoami');
        return "Users/$username/Desktop/";
    }
    return "autocopy_by_adam_borecki/";
}

function edit_volume(&$volumes,$vol_index,$purposes) {
    global $script_info;
    $volume = $volumes[$vol_index];
    echo "*** EDITING volume $vol_index - $volume[name] - avial: $volume[free_dec] used: $volume[percent_used]%\n";
    $new_purpose = "";
    while(!isset($purposes[$new_purpose])){
        echo "What purpose should '$volume[name]' have? (Type your response)\n";
        echo "\t".PURPOSE_SRC." SRC\n";
        echo "\t".PURPOSE_DEST." DEST\n";
        echo "\t".PURPOSE_IGNORE." IGNORE\n";
        echo "New purpose: ";
        $new_purpose = prompt_user_input();
        foreach($purposes as $purpose_icon => $purpose_label){
            if( strtolower($new_purpose) == $purpose_label){
                $new_purpose = $purpose_icon;
                break;
            }
            else if( strtolower($new_purpose) == $purpose_icon." ".$purpose_label){
                $new_purpose = $purpose_icon;
                break;
            }
        }
        if(!isset($purposes[$new_purpose]))
            echo "\tERROR: that was not a valid respone. Please re-try.\n\n";
    }
    $volume['purpose'] = $new_purpose;
    echo "$volume[name]'s purpose is now: $new_purpose ".$purposes[$new_purpose]." \n";
    echo "\n";

    switch($new_purpose){
        case PURPOSE_SRC:
            echo "All files/folders from $volume[name] will be copied. ($script_info[name] does not allow partial copying from SD cards, which could result in human error).\n\n";
            echo "Press ENTER to return to volume list.\n";
            prompt_user_input();
            break;
        case PURPOSE_DEST:
            //echo "Notice: Unfortunately you can't change \$volume[destination_path] because adam hasn't programmed that feature.\n";
            echo "\nNotice: Files will be copied to $volume[name] at the destination path:\n";
            echo "\t$volume[destination_path]\n";
            // echo "Where on the ".PURPOSE_DEST." dest drive? (Please set the \$destination_path)\n";
            // echo "\t(leave blank) = current value: $volume[destination_path]\n";
            // echo "\tcustom = NOT SUPPORTED! Dec. 4, 2017 - you can't change the destination path because Adam hasn't added this feature yet.\n";
            echo "\n";
            echo "Press ENTER to return to volume list.\n";
            $add_this_feature_later = prompt_user_input();
            break;
    }

    $volumes[$vol_index] = $volume;
    echo "Done Editing $vol_index - $volume[name].\n";
    passthru("sleep 0.2");
//    return $volumes;
}

function prepare_agenda($volumes,$label,$purposes){
    $agenda = array();

    $src_volumes = array();
    $dest_volumes = array();
    foreach($volumes as $volume_index => $volume){
        $volume['volume_index'] = $volume_index;
        switch($volume['purpose']){
            case PURPOSE_SRC:
                $src_volumes[] = $volume;
                break;
            case PURPOSE_DEST:
                $dest_volumes[] = $volume;
                break;
        }
    }

    $num_dest_volumes = count($dest_volumes);
    $date_prefix = date("ymd_gia");
    foreach($src_volumes as $src_index => $src_volume){
        //foreach($destinations as $dest_index => $dest_volume)
        // instead of foreach, this for loops rotates the order of destinations
        // like "checkboard" copy, this avoids bottlenecking data transfers by spreading out workload more evenly
        for($i = 0;$i < $num_dest_volumes; $i++){
            $dest_index = ($i + $src_index) % $num_dest_volumes;
            $dest_volume = $dest_volumes[ $dest_index ];
            // vol_name_safe is not necessary because it looks like AppleScript copies over something else?
            //$vol_name_safe = preg_replace("/[^a-zA-Z0-9_.\- ]/","_",$src_volume['name']);
            $agenda[] = array(
                "from" => array(
                    $src_volume['volume_index'],
                    $src_volume['absolute_path']
                ),
                "to" => array(
                    $dest_volume['volume_index'],
                    format_dest_full_path($dest_volume,$date_prefix,$label),//.$vol_name_safe,
                ),
            );
        }
    }

    return $agenda;
}

function display_agenda($agenda){
    /*
    Array
    [0] => Array
        (
            [from] => Array
                (
                    [0] => 2
                    [1] => /Volumes/F8/
                )

            [to] => Array
                (
                    [0] => 0
                    [1] => /Volumes/Borecki "Work" ümlaut12/autocopy_by_adam_borecki/171204_autocopy/
                )

        )
    [1] => Array
        (
            [from] => Array
                (
                    [0] => 2
                    [1] => /Volumes/F8/
                )

            [to] => Array
                (
                    [0] => 1
                    [1] => /Volumes/Borecki Backups x0000/autocopy_by_adam_borecki/171204_autocopy/
                )

        )

    */
    echo "** AGENDA **\n";
    $col1 = 6;
    $col2 = 6;
    echo fix_length("from",$col1);
    echo fix_length("to",$col2);
    echo "absolute paths:";
    echo "\n";
    $prev_from_index = -1;
    foreach($agenda as $item){
        list($from_index,$from_path) = $item['from'];
        list($to_index,$to_path) = $item['to'];
        if($prev_from_index != $from_index){
            echo "\n";
            $prev_from_index = $from_index;
        }
        echo fix_length($from_index,$col1);
        echo fix_length($to_index,$col2);
        echo $from_path;
        echo "  --> will be copied to:";
        echo "\n";
        echo fix_length("",$col1);
        echo fix_length("",$col2);
        echo $to_path;
        echo "\n";
        passthru("sleep 0.1");
    }

}

function format_dest_full_path($volume,$date_prefix,$label){
    $absolute_path = $volume['absolute_path'];
    $destination_path = $volume['destination_path'];
    if(substr($absolute_path,-1)!="/")
        $absolute_path .= "/";
    if(substr($destination_path,-1)!="/")
        $destination_path .= "/";
    if(substr($label,-1)!="/")
        $label .= "/";
    return $absolute_path.$destination_path.$date_prefix." ".$label;
}

function get_bytes_required($volumes){
    $bytes_required = 0;
    foreach($volumes as $volume){
        if($volume['purpose'] == PURPOSE_SRC){
            $bytes_required += $volume['bytes_used'];
        }
    }
}

function volume_has_enough_space($volume,$bytes_required){
    global $config;
    if($volume['free'] - $bytes_required - $config['spare_bytes_buffer'] > 0)
        return true;
    else
        return false;
}

function confirm_num_sources($volumes,$expected_volumes){
    global $script_info;
    $num_sources = 0;
    foreach($volumes as $volume)
        if($volume['purpose'] == PURPOSE_SRC)
            $num_sources++;
    for($i = 0;$i < 10;$i++){
        if($i % 3 == 0) {
            echo " ---- POP QUIZ! ---- \n";
            passthru("sleep 0.2");            
        }
        echo "\n";        
    }
    echo "Look at the SD card(s) or SSD(s) plugged into this machine.\n";
    echo "How many sources do you expect autocopy to have?\n";
    $user_input = "";
    while(!$user_input || !is_numeric($user_input)){
        $user_input = prompt_user_input();
        // prompt user input trims automatically
        //$user_input = trim($user_input);
    }
    if($user_input != $num_sources) {
        echo "Sorry, you entered $user_input, which is INCORRECT.";
        echo "\n";
        echo "$script_info[name] will now exit.";
        echo "\n";
        echo "You must count the exact number of sources and triple check that $script_info[name] will copy the correct  number of SD(s) or SSD(s). The correct answer would have been $num_sources according to this script.\n";
        die("");
    } else if($num_sources != $expected_volumes) {
        echo "Error! Although you were correct, you originally said that you expected $expected_volumes - but there were only $num_sources.\n";
        echo "\n";
        echo "$script_info[name] will now exit.";
        echo "\n";
        echo "Please re-try and start from the beginning.";
        die();
    } else {
        echo "Correct!\n";
        echo "Counting the number of SD card(s) or SSD(s) is possibility of human error, which is why this is so important.\n";
        echo "Press ENTER to go to the next step.\n";
        prompt_user_input();
    }
}

function make_history(){

}

function save_history($history){
    $path = "";
}

function prompt_label(){
    $ready = false;
    echo "Please enter a label for this: (such as: 'last name','concert1,concert2,concert3','SERIES concert', etc.). Special characters will be replaced.\n";
    $label = prompt_user_input();
    while(!$ready){
        $label = iconv("utf-8","ascii//TRANSLIT",$label);
        $label = preg_replace("/[^a-zA-Z0-9,_\\-.  ]/","_",$label);
        if(!$label) {
            $label = "unlabelled";
        }
        echo "\tYour label is: $label\n";
        echo "Press ENTER to continue, or type anything else for your new label. (Press Control + C to quit.\n";
        $new_label = prompt_user_input();
        if(!$new_label){
            $ready = true;
        } else {
            $label = $new_label;
        }
    }
    return $label;   
}