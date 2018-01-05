<?php
/**
 * Name: sameColor
 * Desc: 判断两个颜色是否相似
 * User: BaiYuLong<baiyl3@lenovo.com>
 * Date: 2018-01-02
 * @param $r1
 * @param $g1
 * @param $b1
 * @param $r2
 * @param $g2
 * @param $b2
 * @return bool
 */
function sameColor($r1, $g1, $b1, $r2, $g2, $b2)
{
    $r = ($r1 - $r2) / 256;
    $g = ($g1 - $g2) / 256;
    $b = ($b1 - $b2) / 256;
    $diff = 1 - sqrt($r * $r + $g * $g + $b * $b);
    return $diff >= 0.9;
}

/**
 * Name: distance
 * Desc: 求两点之间的距离
 * User: BaiYuLong<baiyl3@lenovo.com>
 * Date: 2018-01-02
 * @param $x1
 * @param $y1
 * @param $x2
 * @param $y2
 * @return float
 */
function distance($x1, $y1, $x2, $y2)
{
    $w = $x2 - $x1;
    $h = $y2 - $y1;
    return intval(round(sqrt($w * $w + $h * $h)));
}

class Game
{
    //跳偶 RGB
    const HR = 56;
    const HG = 56;
    const HB = 97;

    //背景色RGB
    private $bgr;
    private $bgg;
    private $bgb;

    private $im;    //图片资源
    private $iw;    //图片宽度
    private $ih;    //图片高度

    //值搜索图片中间 1/3 部分
    private $su;    //搜索高度（上）
    private $sd;    //搜索高度（下）
    private $sl;    //搜索宽度（左）
    private $sr;    //搜索宽度（右）

    private $top_xy;    //顶点坐标
    private $top_color; //顶点颜色RGB

    private $bottom_xy;    //底点坐标
    private $bottom_color; //底点颜色RGB

    private $pos = 0;   //目标在左边还是右边 左边 -1 右边 1

    //跳偶的坐标
    private $HX;
    private $HY;
    private $HW;    //跳偶宽度的一半

    public function __construct($file)
    {
        $this->im = imagecreatefrompng($file);
        list($this->iw, $this->ih, $it, $ia) = getimagesize($file);
        $h = round($this->ih / 3, 0);
        //跳偶宽度的一半，大概测量了一下系数是0.0763
        $this->HW = intval(round($this->iw * 0.0763 / 2));
        //最大扫描区域 上下
        $this->su = intval($this->ih - ($h * 2));
        $this->sd = intval($this->ih - $h);
    }

    public function run()
    {
        list($this->HX, $this->HY) = $this->getHeroXY();
        $half_w = round($this->iw / 2, 0);      //宽度的一半
        if ($this->HX < $half_w) {
            //如果跳跃点在图片一半的左边，那么在图片右边区域搜索下一个目标
            $this->sl = $this->HX + $this->HW;  //把跳偶宽度的一半加上，不要扫描跳偶，解决跳偶的高度高于顶点的情况
            $this->sr = $this->iw - 50;         //去掉50px的边，缩小扫描范围
            $this->pos = 1;                     //标记目标位置
        } else if ($this->HX > $half_w) {
            //如果跳跃点在图片一半的右边，那么在图片左边区域搜索下一个目标
            $this->sl = 50;
            $this->sr = $this->HX - $this->HW;  //减去跳偶宽度的一半，不要扫描跳偶，解决跳偶的高度高于顶点的情况
            $this->pos = -1;                    //标记目标位置
        } else {
            //如果正好在中间，那么搜索整个区域
            $this->sl = 50;
            $this->sr = $this->HX - 50;         //去掉50px的边，缩小扫描范围
        }
        $this->getBackgroundColor();
        $this->findTop();
        list($topR, $topG, $topB) = $this->top_color;
        if ($topR == 255 && $topG == 255 && $topB == 255) {
            $distance = distance($this->HX, $this->HY, $this->top_xy[0], ($this->top_xy[1] + $this->HW + 5));
        } else {
            list($dx, $dy, $dr, $dg, $b) = $this->pos > 0 ? $this->findRight() : $this->findLeft();
            $distance = distance($this->HX, $this->HY, $this->top_xy[0], $dy);
        }

        return [$this->HX, $this->HY, intval(round($distance * 1.35))];
    }

    /**
     * Name: getBackgroundColor
     * Desc: 获取背景颜色
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-03
     */
    private function getBackgroundColor()
    {
        $rgb = imagecolorat($this->im, $this->sl, $this->su);
        $this->bgr = ($rgb >> 16) & 0xFF;
        $this->bgg = ($rgb >> 8) & 0xFF;
        $this->bgb = $rgb & 0xFF;
    }

    /**
     * Name: getHeroXY
     * Desc: 获取跳偶的坐标
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-02
     * @return array
     */
    private function getHeroXY()
    {
        $xyArray = [];
        for ($x = 100; $x <= $this->iw - 100; $x++) {
            for ($y = $this->su; $y <= $this->sd; $y++) {
                $rgb = imagecolorat($this->im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r == 56 && $g == 56 && $b == 97) {
                    array_push($xyArray, ['x' => $x, 'y' => $y]);
                    break;
                }
            }
        }
        return self::avgXY($xyArray);
    }

    /**
     * Name: findTop
     * Desc: 寻找顶点坐标和颜色值
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-04
     * @return array
     */
    private function findTop()
    {
        for ($y = $this->su; $y <= $this->sd; $y += 2) {
            for ($x = $this->sl; $x <= $this->sr; $x += 2) {
                $rgb = imagecolorat($this->im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (
                    !sameColor($r, $g, $b, $this->bgr, $this->bgg, $this->bgb) &&
                    !sameColor($r, $g, $b, self::HR, self::HG, self::HB)
                ) {
                    $this->top_xy = [$x, $y];
                    $this->top_color = [$r, $g, $b];
                    break 2;
                }
            }
        }
    }

    /**
     * Name: findLeft
     * Desc: 寻找和顶点颜色相同的左边顶点
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-04
     */
    private function findLeft()
    {
        $left = [];
        for ($x = $this->sl; $x <= $this->sr; $x++) {
            for ($y = $this->sd; $y >= $this->top_xy[1]; $y--) {
                $rgb = imagecolorat($this->im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r == $this->top_color[0] && $g == $this->top_color[1] && $b == $this->top_color[2]) {
                    $left = [$x, $y, $r, $g, $b];
                    break 2;
                }
            }
        }
        return $left;
    }

    /**
     * Name: findRight
     * Desc: 寻找
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-04
     */
    private function findRight()
    {
        $right = [];
        for ($x = $this->sr; $x >= $this->sl ; $x--) {
            for ($y = $this->sd; $y >= $this->top_xy[1]; $y--) {
                $rgb = imagecolorat($this->im, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r == $this->top_color[0] && $g == $this->top_color[1] && $b == $this->top_color[2]) {
                    $right = [$x, $y, $r, $g, $b];
                    break 2;
                }
            }
        }
        return $right;
    }

    /**
     * Name: findBottom
     * Desc: 寻找颜色相同的最低点
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-05
     */
    private function findBottom()
    {
        $this->bottom_xy = [$this->top_xy[0], $this->top_xy[1]];
        for ($y = ($this->top_xy[1] + 10); $y <= $this->sd; $y += 2) {
            $rgb = imagecolorat($this->im, $this->top_xy[0], $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if (
                $r == $this->top_color[0]
                && $g == $this->top_color[1]
                && $b == $this->top_color[2]
                && $y >= $this->bottom_xy[1]
            ) {
                $this->bottom_xy[1] = $y;
                $this->bottom_color = [$r, $g, $b];
            }
        }
        list($br, $bg, $bb) = $this->bottom_color;
        //对于药瓶特殊处理
        $h = $this->bottom_xy[1] - $this->top_xy[1];
        if ($br == 255 && $bg == 255 && $bb == 255 && $h >= 90 && $h <= 110) {
            $this->bottom_xy[1] = $this->top_xy[1] + intval(round(($this->ih * 0.03125)));
        }
    }

    /**
     * Name: avgXY
     * Desc: 对数组的XY坐标求平均值
     * User: BaiYuLong<baiyl3@lenovo.com>
     * Date: 2018-01-02
     * @param array $xyArray
     * @return array
     */
    private static function avgXY($xyArray = [])
    {
        $count = count($xyArray);
        $xArray = array_column($xyArray, 'x');
        $yArray = array_column($xyArray, 'y');
        return [
            intval(round(array_sum($xArray) / $count)),
            intval(round(array_sum($yArray) / $count))
        ];
    }
}


while (true) {
    passthru('adb shell screencap -p /sdcard/tmp/s.png');
    sleep(1);
    $fileName = time();
    passthru("adb pull /sdcard/tmp/s.png ./s/$fileName.png");
    sleep(1);

    $game = new Game("./s/$fileName.png");
    list($x, $y, $t) = $game->run();

    passthru("adb shell input swipe $x $y $x $y $t");
    sleep(1);
}
