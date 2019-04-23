<?php
namespace Src\TableGateways;

use Carbon\Carbon;

class IntervalGateway {

    private $db = null;
    /**
     * This method constructs and sets needed variables
     * @param $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * This method gets all intervals from the DB
     * @return array
     */
    public function findAll()
    {
        $sql = "
            SELECT 
                id, date_start, date_end, price
            FROM
                intervals 
            ORDER BY 
                date_start ASC;
        ";

        $intervals = [];

        try {
            $stmt = $this->db->query($sql);
            while($interval = $stmt->fetch_assoc()) {
                $intervals[] = $interval;
            }
            return (object) $intervals;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    /**
     * This method gets one interval from the DB
     * @param $id
     * @return object
     */
    public function find($id)
    {
        $sql = "
            SELECT 
                id, date_start, date_end, price
            FROM
                intervals
            WHERE id = ? LIMIT 1;
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("i", $intervalId);
            $intervalId = (int) $id;
            // Execute statement
            $stmt->execute();
            $res = $stmt->get_result();
            $interval = $res->fetch_array(MYSQLI_ASSOC);
            return !is_null($interval) ? (object) $interval : null;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }    
    }

    /**
     * This method inserts one interval to the DB
     * @param $input
     * @return int
     */
    public function insert($input)
    {
        $sql = "
            INSERT INTO intervals 
                (date_start, date_end, price)
            VALUES
                (?, ?, ?);
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssd", $intervalStart, $intervalEnd, $price);
            $intervalStart = $input->date_start;
            $intervalEnd = $input->date_end;
            $price = (float) $input->price ?? null;
            // Execute statement
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }    
    }

    /**
     * This method updates one interval on the DB
     * @param $id
     * @param $input
     * @return int
     */
    public function update($id, Array $input)
    {
        $sql = "UPDATE intervals SET ";
        $i = 0;
        $length = count($input);
        $param_type = '';
        $a_params = [];
        foreach ($input as $key => $value) {
            switch (gettype($value)) {
                case 'string':
                    $param_type .= 's';
                    break;
                case 'integer':
                case 'double':
                    $param_type .= 'd';
                    break;
                default:
                    $param_type .= 's';
                    break;
            }

            $a_params[] = & $input[$key];
            
            if ($i == $length - 1) {
                $sql .= "$key = ? ";
            } else  {
                $sql .= "$key = ?, ";
            }
            $i++;
        }

        $sql .= " WHERE id = ?;";
        $param_type .= 'i';
        $a_params[] = & $id;

        array_unshift($a_params, $param_type);

        try {
            $stmt = $this->db->prepare($sql);
            call_user_func_array(array($stmt, 'bind_param'), $a_params);
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }    
    }

    /**
     * This method deletes one interval from the DB
     * @param $id
     * @return int
     */
    public function delete($id)
    {
        $sql = "
            DELETE FROM intervals
            WHERE id = ?;
        ";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("i", $intervalId);
            $intervalId = (int) $id;
            // Execute statement
            $stmt->execute();
            return $stmt->affected_rows;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }    
    }

    /**
     * This method deletes one interval from the DB
     * @return string
     */
    public function cleanTable()
    {
        $sql = "
            TRUNCATE TABLE intervals;
        ";

        try {
            $stmt = $this->db->query($sql);
            return $stmt;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    /**
     * This method gets the intervals adjacent to the input, from DB
     * @param $input
     * @return array
     */
    public function getAdjacentIntervals($input)
    {
        $sql = "
            SELECT
                id, date_start, date_end, price
            FROM
                intervals
            WHERE
                (date_start = ?) OR
                (date_end = ?) ORDER BY date_start ASC
        ";

        $date_start = Carbon::createFromFormat('Y-m-d', $input->date_start->toDateString());
        $date_start->subDay();
        $date_end = Carbon::createFromFormat('Y-m-d', $input->date_end->toDateString());
        $date_end->addDay();

        $intervals = [];

        try {
            $stmt = $this->db->prepare($sql);
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ss", $intervalEnd, $intervalStart);
            $intervalStart = $date_start->toDateString();
            $intervalEnd  = $date_end->toDateString();
            // Execute statement
            $stmt->execute();
            $result = $stmt->get_result();
            
            while($interval = $result->fetch_assoc()) {
                $intervals[] = (object) $interval;
                $this->setDatesToCarbonDates(end($intervals));
            }
            return $intervals;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    /**
     * This method gets the intervals overlaping to the input, from DB
     * @param $input
     * @return array
     */
    public function getOverlapingIntervals($input)
    {
        $sql = "
            SELECT
                id, date_start, date_end, price
            FROM
                intervals
            WHERE
                (date_start BETWEEN ? AND ?) OR
                (date_end BETWEEN ? AND ?) OR
                (? BETWEEN date_start AND date_end) OR
                (? BETWEEN date_start AND date_end) ORDER BY date_start ASC
        ";

        $intervals = [];

        try {
            $stmt = $this->db->prepare($sql);
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssssss", $intervalStart, $intervalEnd, $intervalStart, $intervalEnd, $intervalStart, $intervalEnd);
            $intervalStart = $input->date_start->toDateString();
            $intervalEnd  = $input->date_end->toDateString();
            // Execute statement
            $stmt->execute();
            $result = $stmt->get_result();
            
            while($interval = $result->fetch_assoc()) {
                $intervals[] = (object) $interval;
                $this->setDatesToCarbonDates(end($intervals));
            }
            return $intervals;
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

    }

    /**
     * This method sets date_start and date_end to Carbon dates
     * @param $input
     * @return array
     */
    public function setDatesToCarbonDates($interval) 
    {
        $interval->date_start = Carbon::createFromFormat('Y-m-d', $interval->date_start);
        $interval->date_end = Carbon::createFromFormat('Y-m-d', $interval->date_end);
    }

    /**
     * This method cast an input array to standard object and sets its dates to Carbon dates
     * @param $input
     * @return object
     */
    public function createInterval($input)
    {
        $interval = (object) $input;
        $this->setDatesToCarbonDates($interval);
        return $interval;
    }

}