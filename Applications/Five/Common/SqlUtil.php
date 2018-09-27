<?php
/**
 * SQL工具类
 * Created by PhpStorm.
 * User: song
 * Date: 2017/8/11
 * Time: 17:28
 */

namespace Common;


class SqlUtil
{
    public static function createInsertSql($tablename, $columns, $data)
    {
        $sql = 'INSERT INTO ' . $tablename . ' (';
        //列名
        foreach($columns as $i => $colname){
            if($i < count($columns) - 1){
                $sql .= '`' . $colname . '`,';
            }else{
                $sql .= '`' . $colname . '`) values (';
            }

        }

        //内容
        foreach($columns as $i => $colname){
            if($i < count($columns) - 1){
                if(is_numeric($data[$colname])){
                    $sql .= $data[$colname] . ',';
                }else{
                    $sql .= '\'' . $data[$colname] . '\',';
                }
            }else{
                if(is_numeric($data[$colname])){
                    $sql .= $data[$colname] . ');';
                }else{
                    $sql .= '\'' . $data[$colname] . '\');';
                }
            }

        }

        return $sql;


    }

}