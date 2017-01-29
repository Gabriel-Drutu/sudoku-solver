<?php

namespace App\MyLibraries\SudokuSolver;

class SudokuSolver
{
    protected $solved = false;
    protected $interrupt = false;
    protected $loops = 0;
    protected $debug = false;
    protected $notSet = 81;
    protected $initFieldValues = [1,2,3,4,5,6,7,8,9];
    protected $fields = [];
    
    protected $unsolvedSudoku;
    protected $newSudoku = false;
    protected $isWrong = false;
    
    public function __construct()
    {
        $this->constructFields();
        
        if(empty($_GET['submit-sudoku'])){
           $this->setInitialFieldValues();
           $this->newSudoku = true;
        }
        
        $this->setUnsolvedSudoku();
        $this->verifyIfCorrect();
    }
    
    /**
     * Constructs all 81 of sudoku fields
     * and their position.
     */
    protected function constructFields()
    {
        for($x=1; $x<10; $x++){
            for($y=1; $y<10; $y++){
                $this->fields[] = ['x'=>$x, 'y'=>$y, 'nr'=>[], 'set'=>false];
            }
        }
    }
    
    /**
     * Main trigger
     */
    public function solveSudoku()
    {
        if($this->isWrong){
            return;
        }
        
        $this->recursiveSolve();
    }
   
    /**
     * Get and set field values.
     */
    public function getSetFieldValue($x, $y, $fields = [])
    {
        if(empty($fields)){
            $fields = $this->fields;
        }
        
        foreach($fields as $i=>$field){
            if($field['x'] == $x && $field['y'] == $y)
            {
                
                if(count($field['nr']) == 1){
                    return (string) $field['nr'][0];
                } elseif ($this->debug === true){
                    return implode(',', $field['nr']);
                }
                
                return '';
            }
        }
    }
    
    /**
     * Solve the sudoku recursively
     * if solving algorithms get stuck
     */
    protected function recursiveSolve($fieldNr=0, $backtrack=false)
    {
        $state = $this->RCSFields();
        if($fieldNr < 81){
            if($backtrack === false){
                if($state == 'go'){
                    $this->fillFields();
                    $state = $this->recursiveSolve($fieldNr);
                } elseif ($state == 'stuck'){
                    $state = $this->recursiveSolve($fieldNr, true);
                } 
            } elseif ($backtrack === true){
                $field = $this->getFieldInfo($fieldNr);
                if($field['set'] === true){
                    $state =  $this->recursiveSolve($fieldNr+1, true);
                } elseif ($field['set'] === false){
                    foreach($field['nr'] as $pos){
                        $this->setFieldValue($fieldNr, $pos);
                        $state = $this->recursiveSolve($fieldNr+1, true);
                        
                        if($state != 'complete'){
                            $this->unsetFieldValue($fieldNr);
                        }   else {
                            return $state;
                        }
                    }
                }
            }
        } 
        return $state;
    }
    
    /**
     * Trims field possibilities
     * and returns status
     */
    protected function RCSFields()
    {
        $this->resetAllPos();
        $this->cleanPos();
        return $this->getState(); 
    }
    
    /**
     * Reset all fields that are not set
     */
    protected function resetAllPos()
    {
        foreach($this->fields as $ix => $field){
            if($field['set'] === false){
                $this->fields[$ix]['nr'] = $this->initFieldValues;
            }
        }
    }
    
    /**
     * Mark the fields with only one posibility as set
     */
    protected function fillFields()
    {
        foreach($this->fields as $ix => $field){
            if(count($field['nr']) === 1 && $field['set'] === false){
                $this->fields[$ix]['set'] = true;
            }
        }
    }
    
    /**
     * Solve possibilities
     * using algorithms
     */
    protected function cleanPos()
    {
        // Level 0 is mandatory
        $this->solveLevel_0();
        $this->solveLevel_1();
    }
    
    /**
     * Get the state of the sudoku
     * at call time
     */
    public function getState()
    {
        $unfilledFields = 0;
        $unsolvedFields = 0;
        foreach($this->fields as $field){
            $fieldPosCount = count($field['nr']);
            if($fieldPosCount === 0){
                return 'wrong';
            } elseif ($fieldPosCount === 1 && $field['set'] === false){
                $unfilledFields++;
            } elseif ($fieldPosCount > 1){
                $unsolvedFields++;
            }
        }
        
        if($unfilledFields > 0){
            return 'go';
        } elseif ($unsolvedFields > 0) {
            return 'stuck';
        } else {
            return 'complete';
        }
    }
    
    /**
     * Verify if the inputed sudoku is correct
     */
    protected function verifyIfCorrect()
    {
        for($i=1; $i<10; $i++){
            $this->verifyLine($i, 'x');
            $this->verifyLine($i, 'y');
        }
        for($x=1; $x<4; $x++){
            for($y=1; $y<4; $y++){
                $this->verifySquare($x, $y);
            }
        }
    }
    
    protected function verifyLine($x, $vector)
    {
        $setValues = [];
        foreach($this->fields as $i=>$field){
            if($field[$vector] == $x && $field['set'] === true){
                $setValues[] = $field['nr'][0];
            }
        }
        
        if($this->hasDuplicates($setValues)){
            $this->isWrong = true;
        }
    }
    
    protected function verifySquare($x, $y)
    {
        $setValues = [];
        
        $maxX = $x * 3;
        $maxY = $y * 3;
        $minX = $maxX - 2;
        $minY = $maxY - 2;
        foreach($this->fields as $i=>$field){
            if($field['x'] <= $maxX && $field['x'] >= $minX && $field['set'] === true){
                if($field['y'] <= $maxY && $field['y'] >= $minY ){
                    $setValues[] = $field['nr'][0];
                }
            }
        }
        
        if($this->hasDuplicates($setValues)){
            $this->isWrong = true;
        }
    }
    
    /**
     * Level 0: Base algorithm for trimming field posibilities
     */
    protected function solveLevel_0()
    {
        for($i=1; $i<10; $i++){
            $this->solveSimpleLine($i, 'x');
            $this->solveSimpleLine($i, 'y');
        }
        for($x=1; $x<4; $x++){
            for($y=1; $y<4; $y++){
                $this->solveSimpleSquare($x, $y);
            }
        }
    }
    
    protected function solveSimpleLine($x, $vector)
    {
        $setValues = [];
        foreach($this->fields as $i=>$field){
            if($field[$vector] == $x && $field['set'] === true){
                $setValues[] = $field['nr'][0];
            }
        }
        
        foreach($this->fields as $i=>$field){
            if($field[$vector] == $x && $field['set'] === false){
                $this->fields[$i]['nr'] = array_values(array_diff($field['nr'], $setValues));
            }
        }
    }
      
    protected function solveSimpleSquare($x, $y)
    {
        $setValues = [];
        
        $maxX = $x * 3;
        $maxY = $y * 3;
        $minX = $maxX - 2;
        $minY = $maxY - 2;
        foreach($this->fields as $i=>$field){
            if($field['x'] <= $maxX && $field['x'] >= $minX && $field['set'] === true){
                if($field['y'] <= $maxY && $field['y'] >= $minY ){
                    $setValues[] = $field['nr'][0];
                }
            }
        }
        
        foreach($this->fields as $i=>$field){
            if($field['x'] <= $maxX && $field['x'] >= $minX && $field['set'] === false){
                if($field['y'] <= $maxY && $field['y'] >= $minY ){
                    $this->fields[$i]['nr'] = array_values(array_diff($field['nr'], $setValues));
                }
            }
        }
    }
    
    /**
     * Level 1: intermediary algorithm for trimming field possibilities
     */
    protected function solveLevel_1()
    {
        for($i=1; $i<10; $i++){
            $this->solveIntermLine($i, 'x');
            $this->solveIntermLine($i, 'y');
        }
        for($x=1; $x<4; $x++){
            for($y=1; $y<4; $y++){
                $this->solveIntermSquare($x, $y);
            }
        }
    }
    
    protected function solveIntermLine($x, $vector)
    {
        $allValues = [];
        $setValues = [];
        
        foreach($this->fields as $i=>$field){
            if($field[$vector] == $x && $field['set'] === false){
               $allValues = array_merge($allValues, $field['nr']);
            } elseif($field[$vector] == $x && $field['set'] === true) {
                $setValues = array_merge($setValues, $field['nr']);
            }
        }
        
        $allValues = array_values(array_diff($allValues, $setValues));
        
        $uniqueValues = $this->getUniqueValues($allValues);
        if(empty($uniqueValues)){
            return;
        }
        
        foreach($this->fields as $i=>$field){
            if($field[$vector] == $x && $field['set'] === false){
                foreach($uniqueValues as $j=>$v){
                    if(in_array($v, $field['nr'])){
                        $this->fields[$i]['nr'] = [$v];
                    }
                }
            }
        }
    }
    
    protected function solveIntermSquare($x, $y)
    {            
        $maxX = $x * 3;
        $maxY = $y * 3;
        $minX = $maxX - 2;
        $minY = $maxY - 2;
        
        $allValues = [];
        $setValues = [];
        
        foreach($this->fields as $i=>$field){
            if($field['x'] <= $maxX && $field['x'] >= $minX){
                if($field['y'] <= $maxY && $field['y'] >= $minY ){
                    if ($field['set'] === false){
                       $allValues = array_merge($allValues, $field['nr']); 
                    } elseif ($field['set'] === true){
                        $setValues = array_merge($setValues, $field['nr']);
                    }
                    
                }
            } 
        }
        
        $allValues = array_values(array_diff($allValues, $setValues));
        
        $uniqueValues = $this->getUniqueValues($allValues);
        
        foreach($this->fields as $i=>$field){
            if($field['x'] <= $maxX && $field['x'] >= $minX && $field['set'] === false){
                if($field['y'] <= $maxY && $field['y'] >= $minY ){
                    foreach($uniqueValues as $j=>$v){
                        if(in_array($v, $field['nr'])){
                            $this->fields[$i]['nr'] = [$v];
                        }
                    }
                }
            }
        }
    }
    
    /* Helper methods */
    protected function getUniqueValues(array $bunch)
    {
        $uniqueValues = [];
        $counted_values = array_count_values($bunch);
        foreach($counted_values as $v=>$c){
            if($c == 1){
                $uniqueValues[] = $v;
            }
        }

        return $uniqueValues;
    }
    
    
    protected function removeDuplicates($inputArr)
    {
        asort($inputArr);
        $inputArr = array_unique($inputArr);
        $inputArr = array_values($inputArr);
        
        return $inputArr;
    }
    
    protected function hasDuplicates($inputArr)
    {
        return count($inputArr) !== count(array_unique($inputArr));
    }
    
    
    /**
     * Set values of the sudoku fields
     * from the GET request.
     */
    protected function setUnsolvedSudoku()
    {
      
        foreach($_GET as $key=>$value){
            if (preg_match('/x-\d_y-\d/',$key) && is_numeric($value) && $value > 0 && $value < 10) {
                $rowcol = explode('_', $key);
                $row = explode('-', $rowcol[0]);
                $col = explode('-', $rowcol[1]);
                $x = $row[1];
                $y = $col[1];
                
                $this->setField($x, $y, $value);
            }
        }
    
        $this->unsolvedSudoku = $this->fields;
        //$jsonFields = json_encode($this->fields);
        //$fh = fopen('unsolved_sudoku.json', 'w');
        //fwrite($fh, $jsonFields);
        //fclose($fh);
    }
    
    /**
     * Sets the empty fields to their
     * initial value posibilities.
     */
    protected function setInitialFieldValues()
    {
        foreach($this->fields as $i=>$field)
        {
            if(empty($field['nr'])){
                $this->fields[$i]['nr'] = $this->initFieldValues;
            }
        }
    }
    
    /**
     * Set the value of a field
     * in a certain position.
     */
    protected function setField($x, $y, $nr)
    {
        foreach($this->fields as $i=>$field){
            if($field['x'] == $x && $field['y'] == $y)
            {
                // Probably unnecessary validation
                if(is_numeric($nr) && $nr > 0 && $nr < 10){
                    $this->fields[$i]['nr'] = [ (int) $nr];
                    $this->fields[$i]['set'] = true;
                }
            }
        }
    }
    
    /**
     * Set the value of a field
     * by index of the field
     */
    protected function setFieldValue($fieldIx, $pos)
    {
        $this->fields[$fieldIx]['nr'] = [$pos];
        $this->fields[$fieldIx]['set'] = true;
    }
    
    /**
     * Reset the posibilities of a field
     * by index of the field
     */
    protected function unsetFieldValue($fieldIx)
    {
        $this->fields[$fieldIx]['nr'] = $this->initFieldValues;
        $this->fields[$fieldIx]['set'] = false;
    }
    
    /**
     * Get the field array
     * by index of the field
     */
    protected function getFieldInfo($fieldIx)
    {
        return $this->fields[$fieldIx];
    }
        
    /**
     * Turn debug mode on
     */
    public function turnOnDebug()
    {
        $this->debug = true;
    }
    
    /**
     * Turn debug mode off
     */
    public function turnOffDebug()
    {
        $this->debug = false;
    }
        
    /**
     * Render the html
     */
    public function renderHtml()
    {
        ?>
        <style>
            .clear {
                clear: both;
                height: 5px;
            }
            
            .separate {
                margin-right: 10px;
            }
            
            .sudoku-fields-container {
                margin: 0 auto;
                width: 385px;
            }
            
            .sudoku-field {
                float: left;
            }
            
            .sudoku-field > input {
                height: 40px;
                width: 40px;
                text-align: center;
            }
            
            @media screen and (max-width: 440px){
                .sudoku-field > input {
                     height: 25px;
                     width: 25px;
                 }
                 
                 .sudoku-fields-container {
                     width: 260px;
                 }
                 
            }
        </style>
            <div class="input-sudoku-container col-md-6">
                <p class="explaining text-center">Enter your unsolved sudoku here</p>
                <div class="sudoku-fields-container">
                    <form method="get">
                    <?php                    
                        $unsolvedSudoku = $this->unsolvedSudoku;
          
                        for($x=1; $x<10; $x++){
                            for($y=1; $y<10; $y++){
                                $field_name = 'x-'. $x. '_' . 'y-'. $y;
                                $field_value = $this->getSetFieldValue($x, $y, $unsolvedSudoku);
                                $separator_class = ($y % 3 == 0 && $y % 9 != 0)? ' separate' : '';
                                echo '<div class="sudoku-field'. $separator_class. '">';
                                echo '<input type="text" name="'. $field_name. '" value="'. $field_value .'" size="1" maxlength="1" />';
                                echo '</div>';
                            }
                            echo '<div class="clear"></div>';
                            if($x % 3 == 0){
                                echo '<div class="clear"></div>';
                            }
                        }
                    
                    ?>
                    <input type="submit" class="sudoku-submit-btn btn btn-success form-control" name="submit-sudoku" value="Submit Sudoku" />
                </form>
            </div>
        </div>
        <div class="solved-sudoku-container col-md-6">
            <p class="explaining text-center">See it solved here</p>   
            <div class="sudoku-fields-container">
            <?php
                if(!$this->isWrong):
                    for($x=1; $x<10; $x++){
                        for($y=1; $y<10; $y++){
                            $field_name = 'x-'. $x. '_' . 'y-'. $y;
                            $field_value = $this->newSudoku ? '' : $this->getSetFieldValue($x, $y);
                            $separator_class = ($y % 3 == 0 && $y % 9 != 0)? ' separate' : '';
                            echo '<div class="sudoku-field'. $separator_class. '">';
                            echo '<input type="text" name="'. $field_name. '" value="'. $field_value .'" size="1" maxlength="1" readonly="readonly" />';
                            echo '</div>';
                        }
                        echo '<div class="clear"></div>';
                        if($x % 3 == 0){
                            echo '<div class="clear"></div>';
                        }
                    }
                else:
                    echo '<p class="error-msg">The sudoku you have entered is incorect.</p>';
                endif;
                $urlWithoutGet = strtok($_SERVER["REQUEST_URI"],'?');
                echo '<a href="'. htmlspecialchars($urlWithoutGet). '" class="btn">Reset fields</a>';
            ?>
            </div>
        </div>
        <?php
    }
}