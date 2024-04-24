<?php
    $orp_address = 'https://results.nsra.co.uk/';
    $orp_competition_page_address = "https://results.nsra.co.uk/nsra/results/";
    $orp_team_results_page_address = "https://results.nsra.co.uk/modules/results/league/teams/summary?team_id=";

    header("Content-Type: application/json; charset=UTF-8");

    if (!_isCurlInstalled()) {
        // cURL is not installed, return an error
        $response = [
            'status' => 'error',
            'message' => 'cURL is not installed on this server.',
            'data' => ''
        ];
        echo json_encode($response);
        return;
    }

    $requestMethod = $_SERVER["REQUEST_METHOD"];

    //-----------------------------------------
    // Testing addresses 
    //
    // http://localhost/orp_scraper.php?type=periods
    //
    // http://localhost/orp_scraper.php?type=competition_list&param=Winter 23-24
    //
    // http://localhost/orp_scraper.php?type=team_ids&param=261
    //
    // http://localhost/orp_scraper.php?type=team_results&param=1900
    //
    //

    //uncomment these lines to test locally
    // leave as comments to load in web browser

    //$requestMethod = 'GET';

    //$_GET['type'] = 'periods';    

    //$_GET['type'] = 'competition_list';
    //$_GET['param'] = 'Winter 23-24';

    //$_GET['type'] = 'team_ids';
    //$_GET['param'] = '261';    

    //$_GET['type'] = 'team_results';
    //$_GET['param'] = '1900'; 

    // Handle HTTP requests

    
    switch ($requestMethod) {
       case "GET":
            // Handle GET request

            if (isset($_GET['type'])) {
                $type = $_GET['type'];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'ORP Type required',
                    'data' => ''
                ];
                echo json_encode($response);
                return;
            }

            if (!preg_match('/\b(periods|competition_list|team_ids|team_results)\b/i', $type)) {
                $response = [
                    'status' => 'error',
                    'message' => 'ORP invalid request type',
                    'data' => ''
                ];
                echo json_encode($response);
                return;
            }

            switch ($type) {

                case 'periods':
                    $res = get_competition_periods();
                    echo json_encode($res);
                    break;

                case 'competition_list':

                    if (isset($_GET['param'])) {
                        $param = $_GET['param'];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => "ORP param required for 'competition_list' request",
                            'data' => ''
                        ];
                        echo json_encode($response);
                        return;
                    }

                    $res = get_competition_list($param);
                    echo json_encode($res);
                    break;
                    
                case 'team_ids':

                    if (isset($_GET['param'])) {
                        $param = $_GET['param'];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => "ORP param required for 'team_ids' request",
                            'data' => ''
                        ];
                        echo json_encode($response);
                        return;
                    }

                    $res = get_teamid_list($param);
                    echo json_encode($res);
                    break;

                case 'team_results':

                    if (isset($_GET['param'])) {
                        $param = $_GET['param'];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => "ORP param required for 'team_results' request",
                            'data' => ''
                        ];
                        echo json_encode($response);
                        return;
                    }

                    $res = get_team_results($param);
                    echo json_encode($res);
                    break;

            }
            break;


        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method Not Allowed'
            ]);
    }

    //get team results for teamID x 
    //   Input TeamID (eg. '1900')
    //   Returns html results page
    //   or [0]='Error'
    function get_team_results($team_id) {
        global $orp_team_results_page_address;

        $url = $orp_team_results_page_address . $team_id;
        $html_lines = get_url($url);
        if ($html_lines['status']=='error') {
            //$return = $html_lines;
            $return = [
                'status' => $html_lines['status'],
                'message' => $html_lines['message'],
                'data' => ''
                ];
        } else {
            $return = [
                'status' => 'ok',
                'message' => '',
                'data' => $html_lines['message']
                ];
        }

        return $return;
    }

    //get team name & team id's for competition $comp_id
    //  Input Competition PageID eg. 261
    //  Returns list of TeamID & Team Name
    //  or [0]='Error' ...... 
    function get_teamid_list($comp_id) {
        global $orp_competition_page_address;
        $url = $orp_competition_page_address . $comp_id;
        $html_lines = get_url($url);
        if ($html_lines['status']=='error') {
            return $html_lines;
        }
        $html_lines = (array)$html_lines['message'];
        $team_array = array();
        $ct = 0;
        foreach ($html_lines as $line) {

            if (str_contains($line,'"item team hidden-md-down d-print-block team-members-tooltip"')) {
                $team_id = get_text($line, '"team_id=', '">');
                $team = get_text($line, '">Team', '</div');
                $team_name = get_text($html_lines[$ct + 2], '"item clubname">', '</div>');
                $team_name = $team_name.$team;

                $row_array = [  'team_id' => $team_id, 
                                'team_name' => $team_name];
                $team_array[] = $row_array;

            }
            $ct = $ct + 1;
        }

        $orders = array_column($team_array, 'team_name'); // Extract the 'order' values
        array_multisort($orders, SORT_ASC, $team_array);

        if (empty($team_array)) {
            $return = [
                'status' => 'error',
                'message' => "No data for '".$comp_id."'",
                'data' => ''
                ];
        } else {
            $return = [
                'status' => 'ok',
                'message' => '',
                'data' => $team_array
                ];
        } 

        return $return;
    }   

    //get competition name & page ID's from ORP for $get_period
    //   Input = Competition Period eg. 'Summer 23'
    //   Returns list of Competition Names & Competition PageID
    //   or [0] = 'Error' & [1] = reason
    function get_competition_list($get_period) {

        global $orp_address;

        $period = "";
        $cl = array();

        $html_content = (array)get_url($orp_address);

        if ($html_content['status']=='error') {
            return $html_content;
        }

        foreach ($html_content['message'] as $ln) {

            if (preg_match('/(w">Winter|w">Summer|w">BUCS)/i', $ln)) 
                {
                        $period = get_text($ln,'w">','</a>');
                }

            if ($get_period == $period) {
    
                if (str_contains($ln, '"/nsra/results/'))  { 
                    if (str_contains($ln, 'Ind'))  {              
                        //if Individual Competition do not store
                    } else { 
                        $comppageid = get_text($ln, '"/nsra/results/', '">');
                        $compname = get_text($ln, '">', '</a></li>');
                        //$line_array = array();
                        $line_array = [   'period' => $period, 
                                            'competition_id' => $comppageid,
                                            'competition_name' => $compname];
                        $cl[] = $line_array;
                    }
                }
            }
        }

        if (empty($cl)) {
            $return = [
                'status' => 'error',
                'message' => "No data for '".$get_period."'",
                'data' => ''
                ];
        } else {
            $return = [
                'status' => 'ok',
                'message' => '',
                'data' => $cl
                ];
        }

        return $return;
    }

    //get list of result periods from ORP 
    //   Returns list of Competition Periods. (eg. 'Summer 2023')
    //   or [0]='Error' & [1]='error reason'  
    function get_competition_periods() {

        global $orp_address;

        $period = "";
        $period_array =array();

        $html_content = get_url($orp_address);

        if ($html_content['status']=='error') {
            return $html_content;
        }

        //search html lines for Summer or Winter or BUCS

        $index = 0;
        $content = (array)$html_content['message'];
        foreach ($content as $ln) {
            if (preg_match('/(w">Winter|w">Summer|w">BUCS)/i', $ln)) {
                    $period = get_text($ln,'w">','</a>');
                    $period_array[] = array('id' => $index, 'option'=> $period);
                    $index = $index + 1;
            }
        }

        if (empty($period_array)) {
            $return = [
                'status' => 'error',
                'message' => "No data for ",
                'data' => ''
            ];
        } else {
            $return = [
                'status' => 'ok',
                'message' => '',
                'data' => $period_array,
            ];
        }
        return $return;
    }

    //get data from url
    function get_url($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        if ($errno != 0) {
            $error = curl_error($ch);
            $return = [
                'status' => 'error',
                'message' => $error,
            ];
        } else {
            $response = preg_split("/\r\n|\n|\r/", $response);
            $return = [
                'status' => 'ok',
                'message' => $response
            ];
        }
        curl_close($ch);
        return $return;
    }

    //cut out text from string
    function get_text($line, $start, $end) {
        $st = strpos($line, $start) + strlen($start);
        $ed = strpos($line, $end);
        return substr($line, $st, $ed - $st);
    }

    function _isCurlInstalled() {
        return in_array('curl', get_loaded_extensions());
    }
