<?php
namespace Src\Controller;

use Src\TableGateways\IntervalGateway;
use Carbon\Carbon;

class IntervalController {

    private $db;
    private $requestMethod;
    private $intervalId;

    private $intervalGateway;

    /**
     * This method constructs and sets needed variables
     * @param $db
     * @param $requestMethod
     * @param $intervalId
     */
    public function __construct($db, $requestMethod, $intervalId)
    {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        $this->intervalId = $intervalId;

        $this->intervalGateway = new IntervalGateway($db);
    }

    /**
     * This method process the request depending the requestMethod
     * @return http response
     */
    public function processRequest()
    {
        switch ($this->requestMethod) {
            case 'GET':
                if ($this->intervalId) {
                    $response = $this->getInterval($this->intervalId);
                } else {
                    $response = $this->getAllIntervals();
                };
                break;
            case 'POST':
                $response = $this->createIntervalFromRequest();
                break;
            case 'PUT':
                $response = $this->updateIntervalFromRequest($this->intervalId);
                break;
            case 'DELETE':
                $response = $this->deleteInterval($this->intervalId);
                break;
            case 'OPTIONS':
                $response = $this->cleanTable();
                break;
            default:
                $response = $this->notFoundResponse();
                break;
        }
        header($response['status_code_header']);
        if ($response['body']) {
            echo $response['body'];
        }
    }

    /**
     * This method gets all intervals on DB
     * @return http response and json
     */
    private function getAllIntervals()
    {
        $result = $this->intervalGateway->findAll();
        $response['status_code_header'] = 'HTTP/1.1 200 OK';
        $response['body'] = json_encode($result);
        return $response;
    }

    /**
     * This method gets one interval from the DB
     * @param $id
     * @return http response and json
     */
    private function getInterval($id)
    {
        $result = $this->intervalGateway->find($id);
        if (is_null($result)) {
            return $this->notFoundResponse();
        }
        $response['status_code_header'] = 'HTTP/1.1 200 OK';
        $response['body'] = json_encode($result);
        return $response;
    }

    /**
     * This method creates a new interval
     * @return http response
     */
    private function createIntervalFromRequest()
    {
        $input = (object) json_decode(file_get_contents('php://input'), TRUE);
        
        if (! $this->validateInterval($input)) {
            return $this->unprocessableEntityResponse();
        }
        $this->intervalGateway->setDatesToCarbonDates($input);
        $overlapingIntervals = $this->intervalGateway->getOverlapingIntervals($input);
        $rowsEdited = $this->manageOverlapingIntervals($overlapingIntervals, $input);

        if ($rowsEdited == 0) {
            return $this->unprocessableEntityResponse();
        }
        $response['status_code_header'] = 'HTTP/1.1 201 Created';
        $response['body'] = null;
        return $response;
    }

    /**
     * This method manages the adjacent intervals from one input interval
     * @param $input
     * @param $rowsEdited
     * @return integer
     */
    private function manageAdjacentIntervals($input, $rowsEdited)
    {
        $adjacentIntervals = $this->intervalGateway->getAdjacentIntervals($input);

        if (sizeof($adjacentIntervals) != 0) {
            //there's at least one adjacent interval
            if ($adjacentIntervals[0]->date_end->isBefore($input->date_start)) {
                if ((int) $input->price == (int) $adjacentIntervals[0]->price) {
                //previous interval is the same price, merge them
                $result = $this->mergeIntervalOnEnd($adjacentIntervals[0], $input);
                $rowsEdited += $result;
                if (isset($input->id)) {
                    $result = $this->intervalGateway->delete($input->id);
                    $rowsEdited += $result;
                }
                return $rowsEdited;
                }
            } else {
                if ((int) $input->price == (int) $adjacentIntervals[0]->price) {
                    //next interval is the same price, merge them
                    $result = $this->mergeIntervalOnStart($adjacentIntervals[0], $input);
                    $rowsEdited += $result;
                    if (isset($input->id)) {
                        $result = $this->intervalGateway->delete($input->id);
                        $rowsEdited += $result;
                    }
                    return $rowsEdited;
                }
            }

            if (sizeof($adjacentIntervals) != 1) {
                //there are 2 adjacet intervals
                if ($adjacentIntervals[1]->date_start->isAfter($input->date_end)) {
                    if ((int) $input->price == (int) $adjacentIntervals[1]->price) {
                        //next interval is the same price, merge them
                        $result = $this->mergeIntervalOnStart($adjacentIntervals[1], $input);
                        $rowsEdited += $result;
                        if (isset($input->id)) {
                            $result = $this->intervalGateway->delete($input->id);
                            $rowsEdited += $result;
                        }
                        return $rowsEdited;
                    }
                }
            }
        }

        if (!isset($input->id)) {
            $result = $this->intervalGateway->insert($input);
            $rowsEdited += $result;
            return $rowsEdited;
        }
    }

    /**
     * This method manages the overlaping intervals from one input interval
     * @param $overlapingIntervals
     * @param $input
     * @return integer
     */
    private function manageOverlapingIntervals($overlapingIntervals, $input)
    {
        if (sizeof($overlapingIntervals) == 0) {
            //there's no overlapping, can be inserted
            return $this->manageAdjacentIntervals($input, 0);
        }

        $lowerLimit = $overlapingIntervals[0];

        if (sizeof($overlapingIntervals) == 1) {
            //there's 1 interval overlapping, next, check if the interval is overlaping on an edge or middle
            if ($lowerLimit->date_start->lessThanOrEqualTo($input->date_start) && $lowerLimit->date_end->greaterThanOrEqualTo($input->date_end)) {
                //interval is on the middle
                if ((int) $lowerLimit->price != (int) $input->price) {
                    if ($lowerLimit->date_start->notEqualTo($input->date_start) && $lowerLimit->date_end->notEqualTo($input->date_end)) {
                        //the lowerLimit and input intervals are not equal, create new interval and modify lowerLimit
                        $extraInterval = $this->intervalGateway->createInterval([
                            'date_start'    =>  $input->date_end->toDateString(),
                            'date_end'      =>  $lowerLimit->date_end->toDateString(),
                            'price'         =>  (int) $lowerLimit->price
                        ]);
                        $extraInterval->date_start->addDay();
                        $rowsEdited = $this->modifyIntervalOnEnd($lowerLimit, $input);
                        $result = $this->intervalGateway->insert($extraInterval);
                        $rowsEdited += $result;
                        $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                        $rowsEdited += $result;
                        return $rowsEdited;
                    }

                    if ($lowerLimit->date_start->equalTo($input->date_start) && $lowerLimit->date_end->equalTo($input->date_end)) {
                        //the lowerLimit and input intervals are equal, update price.
                        $rowsEdited = $this->intervalGateway->update($lowerLimit->id, ['price' => (int) $input->price]);
                        return $rowsEdited;
                    }

                    if ($lowerLimit->date_end->equalTo($input->date_end)) {
                        //the lowerLimit and input date_end are equal, modify lowerLimit and insert input.
                        $rowsEdited = $this->modifyIntervalOnEnd($lowerLimit, $input);
                        $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                        $rowsEdited += $result;
                        return $rowsEdited;
                    }

                    if ($lowerLimit->date_start->equalTo($input->date_start)) {
                        //the lowerLimit and input date_start are equal, modify lowerLimit and insert input.
                        $rowsEdited = $this->modifyIntervalOnStart($lowerLimit, $input);
                        $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                        $rowsEdited += $result;
                        return $rowsEdited;
                    }

                    return 0;
                }

                //Interval is on the middle but the price is the same. No modifications needed.
                return 1;
            }

            if ((int) $lowerLimit->price != (int) $input->price) {
                if ($lowerLimit->date_end->between($input->date_start, $input->date_end)) {
                    //interval overlaps on end_date
                    $rowsEdited = $this->modifyIntervalOnEnd($lowerLimit, $input);
                    $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                    $rowsEdited += $result;
                    return $rowsEdited;
                }
                //interval overlaps on start_date
                $rowsEdited = $this->modifyIntervalOnStart($lowerLimit, $input);
                $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                $rowsEdited += $result;
                return $rowsEdited;
            } else {
                if ($lowerLimit->date_end->between($input->date_start, $input->date_end)) {
                    //interval overlaps on end_date
                    $rowsEdited = $this->mergeIntervalOnEnd($lowerLimit, $input);
                    $result = $this->manageAdjacentIntervals($lowerLimit, $rowsEdited);
                    $rowsEdited += $result;
                    return $rowsEdited;
                }
                //interval overlaps on start_date
                $rowsEdited = $this->mergeIntervalOnStart($lowerLimit, $input);
                $result = $this->manageAdjacentIntervals($lowerLimit, $rowsEdited);
                $rowsEdited += $result;
                return $rowsEdited;
            }
        }

        $upperLimit = end($overlapingIntervals);
        
        if (sizeof($overlapingIntervals) >= 2) {
            //there's more than 2 intervals overlaping check if one of them is completly inside new interval, if not change dates on the lowerLimitInterval and upperLimitInterval

            //delete overlaping intervals between lowerLimit and upperLimit
            $rowsEdited = $this->deleteExtraIntervals($overlapingIntervals);

            if ($lowerLimit->date_start->lessThan($input->date_start) && $upperLimit->date_end->greaterThan($input->date_end)) {
                //intervals are in between other 2 or more not equal to the edges dates
                if ((int) $lowerLimit->price != (int) $input->price  && (int) $upperLimit->price != (int) $input->price) {
                    //intervals have different prices
                    $result = $this->modifyIntervalOnStart($upperLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->modifyIntervalOnEnd($lowerLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                    $rowsEdited += $result;
                    return $rowsEdited;
                }

                if ((int) $lowerLimit->price == (int) $input->price) {
                    //lowerLimit and input price are the same, merge lowerLimit and input and modify upperLimit
                    
                    $result = $this->modifyIntervalOnStart($upperLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->mergeIntervalOnEnd($lowerLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($lowerLimit, $rowsEdited);
                    $rowsEdited += $result;
                    return $rowsEdited;
                }

                if ((int) $upperLimit->price == (int) $input->price) {
                    //upperLimit and input price are the same, merge upperLimit and input and modify lowerLimit
                    $result = $this->modifyIntervalOnEnd($lowerLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->mergeIntervalOnStart($upperLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($upperLimit, $rowsEdited);
                    $rowsEdited += $result;
                    return $rowsEdited;
                }
                return 0;
            }

            if ($lowerLimit->date_start->equalTo($input->date_start)) {
                //interval overlaps completly on lowerLimit interval
                if ((int) $upperLimit->price != (int) $input->price) {
                    //upperLimit and input price is different, modify upperLimit with input and delete lowerLimit
                    $result = $this->modifyIntervalOnStart($upperLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                } else {
                    //upperLimit and input prices are the same, merge them and delete uppperLimit
                    $result = $this->mergeIntervalOnStart($upperLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($upperLimit, $rowsEdited);
                }

                //delete lowerLimit interval
                $rowsEdited += $result;
                $result = $this->intervalGateway->delete($lowerLimit->id);
                $rowsEdited += $result;
                return $rowsEdited;
            }

            if ($upperLimit->date_end->equalTo($input->date_end)) {
                //interval overlaps completly on upperLimit interval
                if ((int) $lowerLimit->price != (int) $input->price) {
                    //lowerLimit and input price is different, modify lowerLimit with input and delete upperLimit
                    $result = $this->modifyIntervalOnEnd($lowerLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($input, $rowsEdited);
                } else {
                    //lowerLimit and input prices are the same, merge them and delete uppperLimit
                    $result = $this->mergeIntervalOnEnd($lowerLimit, $input);
                    $rowsEdited += $result;
                    $result = $this->manageAdjacentIntervals($lowerLimit, $rowsEdited);
                }

                //delete lowerLimit interval
                $rowsEdited += $result;
                $result = $this->intervalGateway->delete($upperLimit->id);
                $rowsEdited += $result;
                return $rowsEdited;
            }
        }

        return 0;
    }

    /**
     * This method deletes extra intervals from an array of intervals
     * @param $overlapingIntervals
     * @return integer
     */
    private function deleteExtraIntervals($overlapingIntervals)
    {
        $length = count($overlapingIntervals);
        if ($length == 2) {
            return 0;
        }
        $intervalsToDelete = array_slice($overlapingIntervals, 1, ($length - 1));
        $rowsEdited = 0;

        foreach ($intervalsToDelete as $interval) {
            $result = $this->intervalGateway->delete($interval->id);
            $rowsEdited += $result;
        }
        
        return $rowsEdited;
    }

    /**
     * This method merges an interval on the right by the date_start
     * @param $rightInterval
     * @param $newInterval
     * @return integer
     */
    private function mergeIntervalOnStart($rightInterval, $newInterval)
    {
        $rightInterval->date_start = Carbon::createFromFormat('Y-m-d', $newInterval->date_start->toDateString());
        $rowsEdited = $this->intervalGateway->update($rightInterval->id, ['date_start' => $rightInterval->date_start->toDateString()]);
        return $rowsEdited;
    }

    /**
     * This method merges an interval on the left by the date_end
     * @param $leftInterval
     * @param $newInterval
     * @return integer
     */
    private function mergeIntervalOnEnd($leftInterval, $newInterval)
    {
        $leftInterval->date_end = Carbon::createFromFormat('Y-m-d', $newInterval->date_end->toDateString());
        $rowsEdited = $this->intervalGateway->update($leftInterval->id, ['date_end' => $leftInterval->date_end->toDateString()]);
        return $rowsEdited;
    }

    /**
     * This method modifys an interval on the right by the date_start
     * @param $leftInterval
     * @param $newInterval
     * @return integer
     */
    private function modifyIntervalOnStart($rightInterval, $newInterval)
    {
        $rightInterval->date_start = Carbon::createFromFormat('Y-m-d', $newInterval->date_end->toDateString());
        $rightInterval->date_start->addDay();
        return $this->intervalGateway->update($rightInterval->id, ['date_start' => $rightInterval->date_start->toDateString()]);
    }

    /**
     * This method modifys an interval on the left by the date_end
     * @param $leftInterval
     * @param $newInterval
     * @return integer
     */
    private function modifyIntervalOnEnd($leftInterval, $newInterval)
    {
        $leftInterval->date_end = Carbon::createFromFormat('Y-m-d', $newInterval->date_start->toDateString());
        $leftInterval->date_end->subDay();
        return $this->intervalGateway->update($leftInterval->id, ['date_end' => $leftInterval->date_end->toDateString()]);
    }

    /**
     * This method updates an interval on the DB
     * @param $id
     * @return http response
     */
    private function updateIntervalFromRequest($id)
    {        
        $result = $this->intervalGateway->find($id);
        if (! $result) {
            return $this->notFoundResponse();
        }
        $input = (object) json_decode(file_get_contents('php://input'), TRUE);
        if (! $this->validateInterval($input)) {
            return $this->unprocessableEntityResponse();
        }
        $result = $this->intervalGateway->update($id, (array) $input);
        if ($result <= 0) {
            return $this->unprocessableEntityResponse();
        }
        $response['status_code_header'] = 'HTTP/1.1 200 OK';
        $response['body'] = null;
        return $response;
    }

    /**
     * This method deletes an interval on the DB
     * @param $id
     * @return http response
     */
    private function deleteInterval($id)
    {
        $result = $this->intervalGateway->find($id);
        if (! $result) {
            return $this->notFoundResponse();
        }
        $result = $this->intervalGateway->delete($id);
        if ($result <= 0) {
            return $this->unprocessableEntityResponse();
        }
        $response['status_code_header'] = 'HTTP/1.1 200 OK';
        $response['body'] = null;
        return $response;
    }

    /**
     * This method cleans the table on the DB for a fresh start
     * @return http response
     */
    private function cleanTable()
    {
        $result = $this->intervalGateway->cleanTable();
        if (!$result) {
            return $this->notFoundResponse();
        }
        $response['status_code_header'] = 'HTTP/1.1 200 OK';
        $response['body'] = null;
        return $response;
    }

    /**
     * This method validates if input interval is valid
     * @param $input
     * @return boolean
     */
    private function validateInterval($input)
    {
        if (! isset($input->date_start)) {
            return false;
        }
        if (! isset($input->date_end)) {
            return false;
        }
        if (! isset($input->price)) {
            $input->price = 0;
        }
        return true;
    }

    /**
     * This method returns and http response of Unprocessable Entity
     * @return http response
     */
    private function unprocessableEntityResponse()
    {
        $response['status_code_header'] = 'HTTP/1.1 422 Unprocessable Entity';
        $response['body'] = json_encode([
            'error' => 'Invalid input'
        ]);
        return $response;
    }

    /**
     * This method returns and http response of Not found
     * @return http response
     */
    private function notFoundResponse()
    {
        $response['status_code_header'] = 'HTTP/1.1 404 Not Found';
        $response['body'] = null;
        return $response;
    }
}