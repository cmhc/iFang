<?php
/*---------------------------
 * FangSpider
 * get data from ziroom
 *
 * step 1.get list item
 * step 2.data collection
 * step 3.insert to database
 *
 * data base structure
-----------------------------*/
namespace cmhc\iFang\Spider;

class FangSpider
{

    protected $page = 1;

    protected $maxPage = 250;

    protected $interval = 2;

    protected $PDO = null;

    protected $subway = [
        "10号线",
        "13号线",
        "14号线",
        "15号线",
        "16号线",
        "1号线",
        "2号线",
        "4号线",
        "5号线",
        "6号线",
        "8号线",
        "9号线",
        "大兴线",
        "八通线",
        "昌平线",
        "亦庄线",
        "房山线",
        "机场线",
    ];

    protected $area = [
        "东城",
        "西城",
        "朝阳",
        "海淀",
        "丰台",
        "石景山",
        "通州",
        "昌平",
        "大兴",
        "顺义",
        "房山",
        "亦庄开发区",
    ];

    public function __destruct()
    {
    }

    public function run()
    {
        for( $i=0; $i<$this->maxPage; $i++ ){
            $list = $this->getListItem();
            $data = $this->dataCollection($list);
            $this->insertData($data);
            sleep($this->interval);
        }
    }

    public function setMaxPage($page)
    {
        $this->maxPage = $page;
    }

    public function setInterval($interval)
    {
        $this->interval = $interval;
    }

    protected function getListItem()
    {
        $url = 'http://www.ziroom.com/z/nl/?p=' . $this->page;
        $this->page += 1;
        file_put_contents("continuepage", $this->page);
        $content = file_get_contents($url);
        preg_match('|<ul id="houseList">(.*)</ul>|is', $content, $matches);
        $data = array();

        if (!empty($matches[1])) {
            $content = $matches[1];

            preg_match_all('/<h3><a[^>]*>([^<]*)<\/a><\/h3>/is', $content, $matches);
            $data['name'] = $matches[1];

            /* strip area and  place name */
            preg_match_all('/<h4><a[^>]*>\s*\[([^]]*)\]\s*([^<\s]*)\s*<\/a><\/h4>/is', $content, $matches);
            $areaArray = array_map(function ($matchArea) {
                foreach ($this->area as $area) {
                    if (strpos($matchArea, $area) !== false) {
                        $address = str_replace($area, "", $matchArea);
                        return [$area, $address];
                    }
                }
            }, $matches[1]);
            $data['address'] = $areaArray;

            /* strip  subway line and subway name */
            $subwayArray = array_map(function ($matchSubway) {
                foreach ($this->subway as $subway) {
                    if (strpos($matchSubway, $subway) !== false) {
                        $subwayName = str_replace($subway, "", $matchSubway);
                        return [$subway, $subwayName];
                    }
                }
            }, $matches[2]);
            $data['subway'] = $subwayArray;

            /* strip detail */
            preg_match_all('/<div class="detail">(.*?)<\/div>/is', $content, $matches);
            $detail = array_map(function ($detail) {
                $detail = preg_replace("/\s/", "", $detail);
                $detailArray = explode("|", $detail);
                $detailArray = array_merge($detailArray, explode("</span>", $detailArray[2]));
                return array_filter(array_map(function ($detail) {
                    return strip_tags($detail);
                }, $detailArray));
            }, $matches[1]);
            $data['detail'] = $detail;

            /* strip tolit and  */
            preg_match_all('/<p class="room_tags[^>]*>(.*?)<\/p>/is', $content, $matches);

            $data['feature'] = array_map(function($feature){
                if( strpos($feature, '独卫') !== false ){
                    return '独卫';
                }else if( strpos($feature, '独立阳台') !== false ){
                    return '独立阳台';
                }else{
                    return '';
                }
            },$matches[1]);

            /* strip price */
            preg_match_all('/<p class="price">([^<]*)<span/is', $content, $matches);

            $data['price'] = array_map(function($price){
                return str_replace(array("￥","\t","\n"," "), '', $price);

            },$matches[1]);

            return $data;

        }

        return false;

    }



    protected function dataCollection($list)
    {
        $data = array();
        for( $i=count($list['name'])-1; $i>=0; $i-- ){
            $data[] = array(
                'name'=>$list['name'][$i],
                'area'=>$list['address'][$i][0],
                'address'=>$list['address'][$i][1],
                'subway'=>$list['subway'][$i][0],
                'site'=>$list['subway'][$i][1],
                'sqare'=> floatval(str_replace("㎡","",$list['detail'][$i][0])),
                'floor'=>$list['detail'][$i][1],
                'house_type'=>$list['detail'][$i][3],
                'rent_type' => $list['detail'][$i][4],
                'distance'=> end($list['detail'][$i]),
                'feature' => $list['feature'][$i],
                'price' => intval($list['price'][$i]),
                );
        }
        return $data;
    }

    protected function connectPDO()
    {
        if( $this->PDO ){
            return $this->PDO;
        }

        $this->PDO = new \PDO("sqlite:".__DIR__."/data.db");
        return $this->PDO;

    }

    protected function insertData($data)
    {
        $this->createTable("fang",$data[0]);
        foreach( $data as $d ){
            $this->insertToDatabase('fang', $d);
        }
    }


    protected function insertToDatabase($table, $data)
    {
        $keys = array_keys($data);
        $sqlKeys = implode(',', $keys);
        $newData = array();
        foreach( $data as $k=>$v ){
            $newData[] = ':'.$k;
        }
        $executeData = array_combine($newData, $data);
        $placeholder = implode(",", $newData);
        $sql = "INSERT INTO {$table}($sqlKeys) VALUES($placeholder)";
        $sth = $this->PDO->prepare($sql);
        $sth->execute($executeData);
        $sth->closeCursor();
    }

    protected function createTable($table, $sampleData)
    {
        print_r($sampleData);
        $type = array();
        foreach($sampleData as $key=>$value){
            if( is_integer($value)){
                $type[$key] = 'INTEGER';
            }else if( is_float($value) ){
                $type[$key] = 'REAL';
            }else{
                $type[$key] = 'TEXT';
            }

            if( $key == 'name' ){
                $type[$key] .= " UNIQUE";
            }
        }

        $this->connectPDO();
        $sql = "CREATE TABLE {$table}(id integer PRIMARY KEY AUTOINCREMENT,";
        foreach( $type as $key=>$type ){
            $sql .= " {$key} {$type} NOT NULL,";
        }
        $sql = rtrim($sql,',');
        $sql .= ")";
        $this->PDO->query($sql);
        print_r($this->PDO->errorInfo());
    }
}
