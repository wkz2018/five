<?php
namespace Common;


/**
 * 超级公共类
 * User: szw
 * Date: 2017/2/4
 * Time: 15:56
 */
class CPlus
{
    const version = [100];//Events版本控制
    const timeCount = 1;//time进程数
    const serverCount = [1 => 1];//服务器列表
    const serverVersion = [1 => 100];//time平台版本控制

    /**
     * 万能解析
     * @param $content
     * @param $format
     * @return null
     */
    public static function unPack($content, $format)
    {

        if (!empty($content) && !empty($format)) {
            $to_len = 0;
            $len = 0;
            foreach ($format as $val) {
                $pformat = substr($val, 0, 1);
                $nformat = substr($val, 1);

                switch ($pformat) {
                    case 'C':
                        $format_len = 1;
                        break;
                    case 'N':
                        $format_len = 4;
                        break;
                    case 'n':
                        $format_len = 2;
                        break;
                    case 'a':
                        $format_len = $len;
                        break;
                }
                if ($nformat == 'len') {
                    $unpack_data = unpack($pformat, substr($content, $to_len, $to_len + $format_len));
                    $len = $unpack_data[1];
                } else {
                    if ($pformat == 'a') {
                        $unpack_data = unpack($pformat . $len, substr($content, $to_len, $to_len + $format_len));
                        $back_data[$nformat] = $unpack_data[1];
                    } else {
                        $unpack_data = unpack($pformat, substr($content, $to_len, ($to_len + $format_len)));
                        $back_data[$nformat] = $unpack_data[1];
                    }

                }

                $to_len += $format_len;

            }

            return $back_data;

        }

        return null;
    }
}