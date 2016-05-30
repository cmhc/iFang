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

    public function run()
    {
        $list = $this->getListItem();
        $this->dataCollection();
        $this->insertToDatabase();
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

            preg_match_all('|<h3><a[^>]*>([^<]*)</a></h3>|is', $content, $matches);
            $data['name'] = $matches[1];

            preg_match_all('|<h4><a[^>]*>\s*\[([^]]*)\]\s*([^<\s]*)\s*</a></h4>|is', $content, $matches);

            /* strip area and  place name */
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

            print_r($data);

        }

    }

    protected function dataCollection()
    {

    }

    protected function insertToDatabase()
    {

    }
}
