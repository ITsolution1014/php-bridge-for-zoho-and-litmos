<?php

$mysqli = new mysqli('localhost', "", "", "");

if(isset($_GET['getEmails'])){
    $today = date("Y-m-d");
    $result = $mysqli->query("SELECT user FROM tracking WHERE saved=0 GROUP BY user ORDER BY time_recorded");
    $data = array();
    while($row = mysqli_fetch_array($result)){
        $data[] = $row['user'];
    }
    echo json_encode($data);
}
else if(isset($_POST['u'])){
    $email = $_POST['u'];
    
    $url = "https://api.litmoseu.com/v1.svc/users?source=sampleapp&search=" . $email . "&format=json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey:"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($httpcode > 200) exit;
    
    $users_data = json_decode($resp, true);
    if(count($users_data) == 0) exit;
    $litmos_user_id = $users_data[0]['Id'];
    $litmos_modules = array();
    
    $url1 = "https://api.litmoseu.com/v1.svc/users/" . $litmos_user_id . "/courses?source=sampleapp&format=json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey:"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($httpcode == 200){
        $lcourses = json_decode($resp, true);
        foreach($lcourses as $lcourse){
            $url1 = "https://api.litmoseu.com/v1.svc/users/" . $litmos_user_id . "/courses/" . $lcourse['Id'] . "?source=sampleapp&format=json";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey:"));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $resp = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if($httpcode == 200){
                $litmos_course_detail = json_decode($resp, true);
                foreach($litmos_course_detail['Modules'] as $lmodule){
                    $litmos_modules["" . $lmodule['OriginalId']] = $lmodule;
                }
            }
            else{
                $url1 = "https://api.litmoseu.com/v1.svc/users/" . $litmos_user_id . "/courses/" . $lcourse['Id'] . "?source=sampleapp&format=json";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey: "));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resp = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }
        }
    }
    
    $id = $_POST['id'];
    $name = $_POST['n'];
    $result = $mysqli->query("SELECT token FROM tokens WHERE type='access'");
    $row = mysqli_fetch_array($result);
    $access_token = $row['token'];
    
    $url = "https://www.zohoapis.com/crm/v2/deals/search?criteria=((Email:equals:" . $email . "))";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "Authorization: Zoho-oauthtoken " . $access_token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    curl_close($ch);
    
    $response_data = json_decode($resp, true);
    $clients = $response_data['data'];
    if(!is_array($clients)) exit;
    
    $result = $mysqli->query("SELECT * FROM tracking WHERE user LIKE '" . $email . "' AND saved = 0 ORDER BY time_recorded");
    while($row = mysqli_fetch_array($result)){
        $url = $row['url'];
        if (preg_match('/dashboard/i', $url) || preg_match('/library/i', $url) || preg_match('/achivement/i', $url) || preg_match('/instructor/i', $url) || preg_match('/LearnerSession/i', $url) || preg_match('/account/', $url)) {
            $detail = getBrowser($row['user_agent']);
            $visits_history['Name'] = $name;
            $visits_history['Email'] = $row['user'];
            $visits_history['IP_Address'] = $row['ip'];
            $visits_history['URL'] = $url;
            $visits_history['Time_Spent'] = time_spent($row['time_spent']);
            $visits_history['Browser'] = $detail['name'];
            $visits_history['User_Details'] = $detail['userAgent'];
            $visits_history['Operating_System'] = $detail['platform'];
            $visits_history['Module'] = "";
            if(preg_match('/\/module/', $url)){
                $str_tmp1 = explode("/module/", $url);
                if(count($str_tmp1) > 1){
                    $str_tmp2 = explode("/", $str_tmp1[1]);
                    $visits_history['Module'] = intval($str_tmp2[0]);
                }
            }
            if(preg_match('/moduleId=/', $url)){
                $str_tmp1 = explode("moduleId=", $url);
                if(count($str_tmp1) > 1) $visits_history['Module'] = intval($str_tmp1[1]);
            }
            if($visits_history['Module'] != ""){
                $visits_history['Module'] = "" . $visits_history['Module'];
            }
            $cnt = 0;
            foreach($clients as $client){
                $visits_history['Client'] = array(
                    "name" => $client['Deal_Name'],
                    "id" => $client['id']
                );
                $result1 = $mysqli->query("SELECT count(id) as cid, id, visits_id FROM visits_history WHERE client_id='" . $client['id'] . "' AND tracking_id=" . $row['id']);
                $row1 = mysqli_fetch_array($result1);
                $count = $row1['cid'];
                
                $url = "https://www.zohoapis.com/crm/v2/Tracker_Visits_Records";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                
                if($count == 1){
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    $visits_history['id'] = $row1['visits_id'];
                }
                else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                }
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Zoho-oauthtoken " . $access_token));
                $requestBody = array();
                $recordArray = array();
                $recordArray[] = $visits_history;
                $requestBody["data"] =$recordArray;
                
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resp = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if($httpcode <= 201){
                    $cnt ++;
                    $resp_data = json_decode($resp, true);
                    $data = $resp_data['data'];
                    $details = $data[0]['details'];
                    $idd = $details['id'];
                    if($count == 0) $mysqli->query("INSERT INTO visits_history(client_id, visits_id, tracking_id) VALUES('" . $client['id'] . "', '" . $idd . "', " . $row['id'] . ")");
                }
            }
            if($cnt == count($clients)) $mysqli->query("UPDATE tracking SET saved=1 WHERE id=" . $row['id']);
        }
    }
    
    $result = $mysqli->query("SELECT * FROM tracking WHERE user LIKE '" . $email . "' AND saved = 0 ORDER BY time_recorded");
    if(isset($_POST['cs'])){
        $url = "https://api.litmoseu.com/v1.svc/courses?source=sampleapp&search=" . $_POST['cs'] . "&format=json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey:"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($httpcode < 400){
            $course_data = json_decode($resp, true);
            foreach ($course_data as $course){
                if($course['Code'] == $_POST['cs']){
                    $cid = $course['OriginalId'];
                    while($row = mysqli_fetch_array($result)){
                        $url = $row['url'];
                        if (preg_match('/course\/' . $cid . '/i', $url) || preg_match('/courseid=' . $cid . '/', $url)) {
                            $detail = getBrowser($row['user_agent']);
                            $visits_history['Name'] = $name;
                            $visits_history['Email'] = $row['user'];
                            $visits_history['IP_Address'] = $row['ip'];
                            $visits_history['URL'] = $url;
                            $visits_history['Time_Spent'] = time_spent($row['time_spent']);
                            $visits_history['Browser'] = $detail['name'];
                            $visits_history['User_Details'] = $detail['userAgent'];
                            $visits_history['Operating_System'] = $detail['platform'];
                            $visits_history['Visit_Date'] = date("d-m-Y H:i:s", strtotime($row['time_recorded']));
                            $visits_history['Module'] = "";
                            if(preg_match('/\/module/', $url)){
                                $str_tmp1 = explode("/module/", $url);
                                if(count($str_tmp1) > 1){
                                    $str_tmp2 = explode("/", $str_tmp1[1]);
                                    $visits_history['Module'] = intval($str_tmp2[0]);
                                }
                            }
                            if(preg_match('/moduleId=/', $url)){
                                $str_tmp1 = explode("moduleId=", $url);
                                if(count($str_tmp1) > 1) $visits_history['Module'] = intval($str_tmp1[1]);
                            }
                          
                            if($visits_history['Module'] != ""){
                                $module_id = "" . $visits_history['Module'];
                                $visits_history['Module'] = $litmos_modules[$module_id]['Name'];
                                if($litmos_modules[$module_id]['Passmark'] > 0){
                                    $result2 = $mysqli->query("SELECT count(id) as cid FROM quiz_history WHERE client_id='" . $id . "' AND module_id='" . $module_id . "'");
                                    $row2 = mysqli_fetch_array($result2);
                                    if($row2['cid'] == 0){
                                        $url2 = "https://www.zohoapis.com/crm/v2/Quiz_History";
                                        $quiz_history = array();
                                        $quiz_history["Name"] = "".$litmos_modules[$module_id]['OriginalId'];
                                        $quiz_history['Question'] = $litmos_modules[$module_id]['Name'];
                                        $quiz_history['Scores'] = "" . $litmos_modules[$module_id]['Score'];
                                        $str_tmp = explode("/Date(", $litmos_modules[$module_id]['DateCompleted']);
                                        if(count($str_tmp) > 1){
                                            $time = (int)$str_tmp[1]/1000;
                                            $quiz_history['Passed_Date'] = date("d-m-Y H:i:s", $time);    
                                        }
                                        else $quiz_history['Passed_Date'] = "";
                                        $str_tmp1 = explode("/Date(", $litmos_modules[$module_id]['StartDate']);
                                        if(count($str_tmp1) > 1){
                                            $time1 = (int)$str_tmp1[1]/1000;
                                            $quiz_history['Started_Date'] = date("d-m-Y H:i:s", $time1);
                                        }
                                        else $quiz_history['Started_Date'] = "";
                                        $quiz_history['Attempts'] = "" . $litmos_modules[$module_id]['Attempt'];
                                        $quiz_history['Completed'] = $litmos_modules[$module_id]['Completed'] ? "Oui" : "Non";
                                        $quiz_history['Client'] = array(
                                            "name" => $name,
                                            "id" => $id
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url2);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Zoho-oauthtoken " . $access_token));
                                        $requestBody = array();
                                        $recordArray = array();
                                        $recordArray[] = $quiz_history;
                                        $requestBody["data"] =$recordArray;
                                        
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        $resp = curl_exec($ch);
                                        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        curl_close($ch);
                                        if($httpcode == 201){
                                            $mysqli->query("INSERT INTO quiz_history(client_id, module_id) VALUES('" . $id . "', '" . $module_id . "')");
                                        }
                                    }
                                }
                            }
                            $visits_history['Client'] = array(
                                "name" => $name,
                                "id" => $id
                            );
                            $result1 = $mysqli->query("SELECT count(id) as cid, id, visits_id FROM visits_history WHERE client_id='" . $id . "' AND tracking_id=" . $row['id']);
                            $row1 = mysqli_fetch_array($result1);
                            $count = $row1['cid'];
                            
                            $url = "https://www.zohoapis.com/crm/v2/Tracker_Visits_Records";
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            
                            if($count == 1){
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                $visits_history['id'] = $row1['visits_id'];
                            }
                            
                            
                            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Zoho-oauthtoken " . $access_token));
                            $requestBody = array();
                            $recordArray = array();
                            $recordArray[] = $visits_history;
                            $requestBody["data"] =$recordArray;
                            
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $resp = curl_exec($ch);
                            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if($httpcode <= 201){
                                $resp_data = json_decode($resp, true);
                                $data = $resp_data['data'];
                                $details = $data[0]['details'];
                                $idd = $details['id'];
                                if($count == 0) $mysqli->query("INSERT INTO visits_history(client_id, visits_id, tracking_id) VALUES('" . $id . "', '" . $idd . "', " . $row['id'] . ")");
                                $mysqli->query("UPDATE tracking SET saved=1 WHERE id=" . $row['id']);
                            }
                        }
                    }
                    break;
                }
            }
        }
    }
    else if(isset($_POST['lp'])){
        $url = "https://api.litmoseu.com/v1.svc/learningpaths?source=sampleapp&format=json";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json", "apikey:"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($httpcode < 400){
            $LP_data = json_decode($resp, true);
            foreach ($LP_data as $lp){
                if($lp['Name'] == $_POST['lp']){
                    $cid = $lp['OriginalId'];
                    $lid = $lp['Id'];
                    while($row = mysqli_fetch_array($result)){
                        $url = $row['url'];
                        if (preg_match('/LearningPath\/' . $cid . '/i', $url) || preg_match('/LPId=' . $cid . '/', $url)) {
                            $detail = getBrowser($row['user_agent']);
                            $visits_history['Name'] = $name;
                            $visits_history['Email'] = $row['user'];
                            $visits_history['IP_Address'] = $row['ip'];
                            $visits_history['URL'] = $url;
                            $visits_history['Time_Spent'] = time_spent($row['time_spent']);
                            $visits_history['Browser'] = $detail['name'];
                            $visits_history['User_Details'] = $detail['userAgent'];
                            $visits_history['Operating_System'] = $detail['platform'];
                            $visits_history['Visit_Date'] = date("d-m-Y H:i:s", strtotime($row['time_recorded']));
                            $visits_history['Module'] = "";
                            if(preg_match('/\/module/', $url)){
                                $str_tmp1 = explode("/module/", $url);
                                if(count($str_tmp1) > 1){
                                    $str_tmp2 = explode("/", $str_tmp1[1]);
                                    $visits_history['Module'] = intval($str_tmp2[0]);
                                }
                            }
                            if(preg_match('/moduleId=/', $url)){
                                $str_tmp1 = explode("moduleId=", $url);
                                if(count($str_tmp1) > 1) $visits_history['Module'] = intval($str_tmp1[1]);
                            }
                            if($visits_history['Module'] != ""){
                                $module_id = "" . $visits_history['Module'];
                                $visits_history['Module'] = $litmos_modules[$module_id]['Name'];
                                if($litmos_modules[$module_id]['Passmark'] > 0){
                                    $result2 = $mysqli->query("SELECT count(id) as cid FROM quiz_history WHERE client_id='" . $id . "' AND module_id='" . $module_id . "'");
                                    $row2 = mysqli_fetch_array($result2);
                                    if($row2['cid'] == 0){
                                        $url2 = "https://www.zohoapis.com/crm/v2/Quiz_History";
                                        $quiz_history = array();
                                        $quiz_history["Name"] = "".$litmos_modules[$module_id]['OriginalId'];
                                        $quiz_history['Question'] = $litmos_modules[$module_id]['Name'];
                                        $quiz_history['Scores'] = "" . $litmos_modules[$module_id]['Score'];
                                        $str_tmp = explode("/Date(", $litmos_modules[$module_id]['DateCompleted']);
                                        if(count($str_tmp) > 1){
                                            $time = (int)$str_tmp[1]/1000;
                                            $quiz_history['Passed_Date'] = date("d-m-Y H:i:s", $time);    
                                        }
                                       
                                        $str_tmp1 = explode("/Date(", $litmos_modules[$module_id]['StartDate']);
                                        if(count($str_tmp1) > 1){
                                            $time1 = (int)$str_tmp1[1]/1000;
                                            $quiz_history['Started_Date'] = date("d-m-Y H:i:s", $time1);
                                        }
                                        else $quiz_history['Started_Date'] = "";
                                        $quiz_history['Attempts'] = "" . $litmos_modules[$module_id]['Attempt'];
                                        $quiz_history['Completed'] = $litmos_modules[$module_id]['Completed'] ? "Oui" : "Non";
                                        $quiz_history['Client'] = array(
                                            "name" => $name,
                                            "id" => $id
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url2);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Zoho-oauthtoken " . $access_token));
                                        $requestBody = array();
                                        $recordArray = array();
                                        $recordArray[] = $quiz_history;
                                        $requestBody["data"] =$recordArray;
                                        
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        $resp = curl_exec($ch);
                                        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        curl_close($ch);
                                   
                                    }
                                }
                            }
                            
                            $visits_history['Client'] = array(
                                "name" => $name,
                                "id" => $id
                            );
                            $result1 = $mysqli->query("SELECT count(id) as cid, id, visits_id FROM visits_history WHERE client_id='" . $id . "' AND tracking_id=" . $row['id']);
                            $row1 = mysqli_fetch_array($result1);
                            $count = $row1['cid'];
                            
                            $url = "https://www.zohoapis.com/crm/v2/Tracker_Visits_Records";
                            
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            
                            if($count == 1){
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                $visits_history['id'] = $row1['visits_id'];
                            }
                            
                            
                            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Zoho-oauthtoken " . $access_token));
                            $requestBody = array();
                            $recordArray = array();
                            $recordArray[] = $visits_history;
                            $requestBody["data"] =$recordArray;
                            
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $resp = curl_exec($ch);
                            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if($httpcode <= 201){
                                $resp_data = json_decode($resp, true);
                                $data = $resp_data['data'];
                                $details = $data[0]['details'];
                                $idd = $details['id'];
                                if($count == 0) $mysqli->query("INSERT INTO visits_history(client_id, visits_id, tracking_id) VALUES('" . $id . "', '" . $idd . "', " . $row['id'] . ")");
                                $mysqli->query("UPDATE tracking SET saved=1 WHERE id=" . $row['id']);
                            }
                        }
                    }
                    break;
                }
            }
        }
    }
    else die("bad request");
    
}
else die("bad request");

function getBrowser($u_agent)
{
    $bname = 'Unknown';
    $platform = 'Unknown';
    $version= "";
    $ub= "";

    //First get the platform?
    if (preg_match('/linux/i', $u_agent)) {
        $platform = 'Linux';
    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
        $platform = 'MAC';
    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {
        $platform = 'Windows';
    }
   
    // Next get the name of the useragent yes seperately and for good reason
    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Internet Explorer';
        $ub = "MSIE";
    }
    elseif(preg_match('/Firefox/i',$u_agent))
    {
        $bname = 'Mozilla Firefox';
        $ub = "Firefox";
    }
    elseif(preg_match('/Chrome/i',$u_agent))
    {
        $bname = 'Google Chrome';
        $ub = "Chrome";
    }
    elseif(preg_match('/Safari/i',$u_agent))
    {
        $bname = 'Apple Safari';
        $ub = "Safari";
    }
    elseif(preg_match('/Opera/i',$u_agent))
    {
        $bname = 'Opera';
        $ub = "Opera";
    }
    elseif(preg_match('/Netscape/i',$u_agent))
    {
        $bname = 'Netscape';
        $ub = "Netscape";
    }
   
    // finally get the correct version number
    $known = array('Version', $ub, 'other');
    $pattern = '#(?<browser>' . join('|', $known) .
    ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
    if (!preg_match_all($pattern, $u_agent, $matches)) {
        // we have no matching number just continue
    }
   
    // see how many we have
    $i = count($matches['browser']);
    if ($i != 1) {
        //we will have two since we are not using 'other' argument yet
        //see if version is before or after the name
        if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
            $version= $matches['version'][0];
        }
        else {
            $version= $matches['version'][1];
        }
    }
    else {
        $version= $matches['version'][0];
    }
   
    // check if we have a number
    if ($version==null || $version=="") {$version="?";}
   
    return array(
        'userAgent' => $u_agent,
        'name'      => $bname,
        'version'   => $version,
        'platform'  => $platform,
        'pattern'    => $pattern
    );
}

function time_spent($time_spent){
    $tt = "";
    if($time_spent > 4500) $time_spent = 4500;
    $time_spent = round($time_spent);
    $s = $time_spent % 60;
    $n = round(($time_spent - $s) / 60);
    $m = $n % 60;
    $h = round(($n - $m) / 60);
    if($s > 0) $tt = $s . " Second";
    if($s > 1) $tt = $tt."s";
    if($m > 1) $tt = $m . " Minutes " . $tt;
    else if($m > 0) $tt = $m . " Minute " . $tt;
    if($h > 1) $tt = $h . " Hours " . $tt;
    else if($h > 0) $tt = $h . "Hour" . $tt;
    if($tt == '') $tt = 'NO ACTION';
    return $tt;
}
