<?php
// Spider.php
// Converted from 51吸瓜.py to PHP
// Requires phpQuery (https://github.com/phpquery/phpquery) and curl extension
require_once 'phpQuery/phpQuery.php';

class Spider {
    private $domain = 'https://cg51.com';
    private $proxies = [];
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'sec-ch-ua' => '"Not/A)Brand";v="8", "Chromium";v="134", "Google Chrome";v="134"',
        'Accept-Language' => 'zh-CN,zh;q=0.9'
    ];
    private $host;

    public function __construct($extend = '{}') {
        $this->host = $this->hostLate($this->getHosts());
        $this->headers['Origin'] = $this->host;
        $this->headers['Referer'] = $this->host . '/';
        // Simulate Python's threading by calling getCnh synchronously
        $this->getCnh();
    }

    public function getName() {
        // Placeholder method
        return null;
    }

    public function isVideoFormat($url) {
        // Placeholder method
        return false;
    }

    public function manualVideoCheck() {
        // Placeholder method
        return false;
    }

    public function destroy() {
        // Placeholder method
    }

    public function homeContent($filter) {
        $response = $this->makeRequest($this->host);
        $doc = phpQuery::newDocument($response);
        $result = [];
        $classes = [];

        foreach (pq('.navbar-nav.mr-auto li', $doc) as $li) {
            $li = pq($li);
            if ($li->find('ul')->length) {
                foreach ($li->find('ul li') as $subLi) {
                    $subLi = pq($subLi);
                    $classes[] = [
                        'type_name' => $subLi->find('a')->text(),
                        'type_id' => trim($subLi->find('a')->attr('href'))
                    ];
                }
            } else {
                $classes[] = [
                    'type_name' => $li->find('a')->text(),
                    'type_id' => trim($li->find('a')->attr('href'))
                ];
            }
        }

        $result['class'] = $classes;
        $result['list'] = $this->getList(pq('#index article a', $doc));
        return $result;
    }

    public function homeVideoContent() {
        // Placeholder method
        return [];
    }

    public function categoryContent($tid, $pg, $filter, $extend) {
        $result = [];
        if (strpos($tid, '@folder') !== false) {
            $id = str_replace('@folder', '', $tid);
            $videos = $this->getFod($id);
        } else {
            $url = $this->host . $tid . $pg;
            $response = $this->makeRequest($url);
            $doc = phpQuery::newDocument($response);
            $videos = $this->getList(pq('#archive article a', $doc), $tid);
        }

        $result['list'] = $videos;
        $result['page'] = $pg;
        $result['pagecount'] = strpos($tid, '@folder') !== false ? 1 : 99999;
        $result['limit'] = 90;
        $result['total'] = 999999;
        return $result;
    }

    public function detailContent($ids) {
        $url = strpos($ids[0], 'http') === 0 ? $ids[0] : $this->host . $ids[0];
        $response = $this->makeRequest($url);
        $doc = phpQuery::newDocument($response);
        $vod = ['vod_play_from' => '51吸瓜'];
        $did = pq('script[data-api]', $doc)->attr('data-api');

        try {
            $clist = [];
            if (pq('.tags .keywords a', $doc)->length) {
                foreach (pq('.tags .keywords a', $doc) as $a) {
                    $a = pq($a);
                    $title = $a->text();
                    $href = $a->attr('href');
                    $clist[] = '[a=cr:' . json_encode(['id' => $href, 'name' => $title]) . '/]' . $title . '[/a]';
                }
                $vod['vod_content'] = implode(' ', $clist);
            } else {
                $vod['vod_content'] = pq('.post-title', $doc)->text();
            }
        } catch (Exception $e) {
            $vod['vod_content'] = pq('.post-title', $doc)->text();
        }

        try {
            $plist = [];
            if (pq('.dplayer', $doc)->length) {
                $index = 1;
                foreach (pq('.dplayer', $doc) as $dplayer) {
                    $dplayer = pq($dplayer);
                    $config = json_decode($dplayer->attr('data-config'), true);
                    $plist[] = "视频{$index}\${$did}_dm_{$config['video']['url']}";
                    $index++;
                }
                $vod['vod_play_url'] = implode('#', $plist);
            } else {
                $vod['vod_play_url'] = "可能没有视频\${$url}";
            }
        } catch (Exception $e) {
            $vod['vod_play_url'] = "可能没有视频\${$url}";
        }

        return ['list' => [$vod]];
    }

    public function searchContent($key, $quick, $pg = "1") {
        $url = $this->host . "/search/{$key}/{$pg}";
        $response = $this->makeRequest($url);
        $doc = phpQuery::newDocument($response);
        return [
            'list' => $this->getList(pq('#archive article a', $doc)),
            'page' => $pg
        ];
    }

    public function playerContent($flag, $id, $vipFlags) {
        list($did, $pid) = explode('_dm_', $id);
        $p = preg_match('/\.(m3u8|mp4|flv|ts|mkv|mov|avi|webm)/', $pid) ? 0 : 1;
        if (!$p) {
            $pid = $this->getProxyUrl() . "&pdid=" . urlencode($id) . "&type=m3u8";
        }
        return [
            'parse' => $p,
            'url' => $pid,
            'header' => $this->headers
        ];
    }

    public function localProxy($param) {
        try {
            $xtype = isset($param['type']) ? $param['type'] : '';
            if ($xtype === 'm3u8') {
                list($path, $url) = explode('_dm_', urldecode($param['pdid']));
                $response = $this->makeRequest($url);
                $lines = explode("\n", trim($response));
                $times = 0.0;
                foreach ($lines as $line) {
                    if (strpos($line, '#EXTINF:') === 0) {
                        $times += floatval(str_replace(',', '', explode(':', $line)[1]));
                    }
                }
                // Simulate background task synchronously
                $this->someBackgroundTask($path, (int)$times);
                return [200, 'text/plain', $response];
            } elseif ($xtype === 'xdm') {
                $url = $this->host . urldecode($param['path']);
                $response = json_decode($this->makeRequest($url), true);
                $dms = [];
                foreach ($response as $item) {
                    $text = isset($item['text']) ? trim($item['text']) : '';
                    if ($text) {
                        $dms[] = $text;
                    }
                    if (isset($item['children'])) {
                        foreach ($item['children'] as $child) {
                            $ctext = isset($child['text']) ? trim($child['text']) : '';
                            if ($ctext) {
                                if (strpos($ctext, '@') !== false) {
                                    $dms[] = trim(explode(' ', $ctext, 2)[1]);
                                } else {
                                    $dms[] = $ctext;
                                }
                            }
                        }
                    }
                }
                return $this->xml($dms, (int)$param['times']);
            }

            $url = $this->d64($param['url']);
            if (preg_match("/loadBannerDirect\('([^']*)'/", $url, $match)) {
                $url = $match[1];
            }
            $response = $this->makeRequest($url, true);
            return [200, $response['content_type'], $this->aesImg($response['content'])];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [500, 'text/html', ''];
        }
    }

    private function someBackgroundTask($path, $times) {
        try {
            sleep(1);
            $purl = $this->getProxyUrl() . "&path=" . urlencode($path) . "&times={$times}&type=xdm";
            $this->fetch("http://127.0.0.1:9978/action?do=refresh&type=danmaku&path=" . urlencode($purl));
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    private function xml($dms, $times) {
        try {
            $tsrt = "共有" . count($dms) . "条弹幕来袭！！！";
            $danmustr = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $danmustr .= "<i>\n";
            $danmustr .= "\t<chatserver>chat.xtdm.com</chatserver>\n";
            $danmustr .= "\t<chatid>88888888</chatid>\n";
            $danmustr .= "\t<mission>0</mission>\n";
            $danmustr .= "\t<maxlimit>99999</maxlimit>\n";
            $danmustr .= "\t<state>0</state>\n";
            $danmustr .= "\t<real_name>0</real_name>\n";
            $danmustr .= "\t<source>k-v</source>\n";
            $danmustr .= "\t<d p=\"0,5,25,16711680,0\">{$tsrt}</d>\n";

            foreach ($dms as $i => $dm) {
                $base_time = ($i / count($dms)) * $times;
                $dm0 = $base_time + (rand(-3000, 3000) / 1000);
                $dm0 = round(max(0, min($dm0, $times)), 1);
                $dm2 = $this->getColor();
                $dm4 = preg_replace('/[<>&\x00\b]/', '', $dm);
                $danmustr .= "\t<d p=\"{$dm0},1,25,{$dm2},0\">{$dm4}</d>\n";
            }
            $danmustr .= "</i>";
            return [200, "text/xml", $danmustr];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [500, 'text/html', ''];
        }
    }

    private function getColor() {
        if (rand(0, 99) < 10) {
            $h = lcg_value();
            $s = lcg_value() * (1.0 - 0.7) + 0.7;
            $v = lcg_value() * (1.0 - 0.8) + 0.8;
            list($r, $g, $b) = $this->hsvToRgb($h, $s, $v);
            $r = (int)($r * 255);
            $g = (int)($g * 255);
            $b = (int)($b * 255);
            return ($r << 16) + ($g << 8) + $b;
        }
        return '16777215';
    }

    private function hsvToRgb($h, $s, $v) {
        $i = floor($h * 6);
        $f = $h * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        switch ($i % 6) {
            case 0: return [$v, $t, $p];
            case 1: return [$q, $v, $p];
            case 2: return [$p, $v, $t];
            case 3: return [$p, $q, $v];
            case 4: return [$t, $p, $v];
            case 5: return [$v, $p, $q];
        }
        return [0, 0, 0];
    }

    private function e64($text) {
        try {
            return base64_encode($text);
        } catch (Exception $e) {
            error_log("Base64 encoding error: " . $e->getMessage());
            return "";
        }
    }

    private function d64($encoded_text) {
        try {
            return base64_decode($encoded_text);
        } catch (Exception $e) {
            error_log("Base64 decoding error: " . $e->getMessage());
            return "";
        }
    }

    private function getHosts() {
        $url = $this->domain;
        $curl = $this->getCache('host_51cn');
        if ($curl) {
            try {
                $response = $this->makeRequest($curl);
                $doc = phpQuery::newDocument($response);
                $data = pq('a', $doc)->attr('href');
                if ($data) {
                    $parsed_url = parse_url($data);
                    $url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }

        try {
            $response = $this->makeRequest($url);
            $doc = phpQuery::newDocument($response);
            preg_match("/Base64\.decode\('([^']+)'\)/", pq('script:last', $doc)->text(), $html_match);
            if (!$html_match) {
                throw new Exception("未找到html");
            }
            $html = base64_decode($html_match[1]);
            $sub_doc = phpQuery::newDocument($html);
            $script = pq('script:eq(-4)', $sub_doc)->text();
            return $this->hstr($script);
        } catch (Exception $e) {
            error_log("获取: " . $e->getMessage());
            return "";
        }
    }

    private function getCnh() {
        $response = $this->makeRequest($this->host . '/homeway.html');
        $doc = phpQuery::newDocument($response);
        $url = pq('.post-content[itemprop="articleBody"] blockquote p:eq(0) a', $doc)->attr('href');
        $parsed_url = parse_url($url);
        $host = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        $this->setCache('host_51cn', $host);
    }

    private function hstr($html) {
        $html = preg_replace("/(backupLine\s*=\s*\[\])\s+(words\s*=)/", '$1, $2', $html);
        $data = <<<JS
var Vx = {
    range: function(start, end) {
        var result = [];
        for (var i = start; i < end; i++) {
            result.push(i);
        }
        return result;
    },
    map: function(array, callback) {
        var result = [];
        for (var i = 0; i < array.length; i++) {
            result.push(callback(array[i], i, array));
        }
        return result;
    }
};

Array.prototype.random = function() {
    return this[Math.floor(Math.random() * this.length)];
};

var location = {
    protocol: "https:"
};

function executeAndGetResults() {
    var allLines = lineAry.concat(backupLine);
    var resultStr = JSON.stringify(allLines);
    return resultStr;
};
{$html}
executeAndGetResults();
JS;

        return $this->pQjs($data);
    }

    private function pQjs($js_code) {
        try {
            // Simplified JavaScript parsing: extract words and domain suffix
            preg_match("/words\s*=\s*'([^']+)'/", $js_code, $words_match);
            preg_match("/lineAry\s*=.*?words\.random\(\)\s*\+\s*'\.([^']+)'/", $js_code, $domain_match);
            if (!$words_match || !$domain_match) {
                throw new Exception("未找到words或域名");
            }
            $words = explode(',', $words_match[1]);
            $domain_suffix = $domain_match[1];
            $random_word = $words[array_rand($words)];
            return ["https://{$random_word}.{$domain_suffix}"];
        } catch (Exception $e) {
            error_log("执行失败: " . $e->getMessage());
            return [];
        }
    }

    private function getDomains() {
        $response = $this->makeRequest($this->domain);
        $doc = phpQuery::newDocument($response);
        preg_match("/Base64\.decode\('([^']+)'\)/", pq('script:last', $doc)->text(), $html_match);
        if (!$html_match) {
            throw new Exception("未找到html");
        }
        $html = base64_decode($html_match[1]);
        preg_match("/words\s*=\s*'([^']+)'/", $html, $words_match);
        if (!$words_match) {
            throw new Exception("未找到words");
        }
        preg_match("/lineAry\s*=.*?words\.random\(\)\s*\+\s*'\.([^']+)'/", $html, $domain_match);
        if (!$domain_match) {
            throw new Exception("未找到主域名");
        }
        $words = explode(',', $words_match[1]);
        $domain_suffix = $domain_match[1];
        $domains = [];
        for ($i = 0; $i < 3; $i++) {
            $random_word = $words[array_rand($words)];
            $domains[] = "https://{$random_word}.{$domain_suffix}";
        }
        return $domains;
    }

    private function getFod($id) {
        $url = $this->host . $id;
        $response = $this->makeRequest($url);
        $doc = phpQuery::newDocument($response);
        $vdata = pq('.post-content[itemprop="articleBody"]', $doc);
        $remove_selectors = ['.txt-apps', '.line', 'blockquote', '.tags', '.content-tabs'];
        foreach ($remove_selectors as $selector) {
            $vdata->find($selector)->remove();
        }
        $p = $vdata->find('p');
        $videos = [];
        foreach (pq('h2', $vdata) as $i => $h2) {
            $h2 = pq($h2);
            $c = $i * 2;
            $videos[] = [
                'vod_id' => $p->eq($c)->find('a')->attr('href'),
                'vod_name' => $p->eq($c)->text(),
                'vod_pic' => $this->getProxyUrl() . "&url=" . $this->e64($p->eq($c + 1)->find('img')->attr('data-xkrkllgl')),
                'vod_remarks' => $h2->text()
            ];
        }
        return $videos;
    }

    private function hostLate($url_list) {
        if (is_string($url_list)) {
            $urls = array_map('trim', explode(',', $url_list));
        } else {
            $urls = $url_list;
        }

        if (count($urls) <= 1) {
            return $urls[0] ?? '';
        }

        $results = [];
        foreach ($urls as $url) {
            try {
                $start_time = microtime(true);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) { return "$k: $v"; }, array_keys($this->headers), $this->headers));
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http_code == 200) {
                    $delay = (microtime(true) - $start_time) * 1000;
                    $results[$url] = $delay;
                } else {
                    $results[$url] = PHP_INT_MAX;
                }
            } catch (Exception $e) {
                $results[$url] = PHP_INT_MAX;
            }
        }

        $min = array_keys($results, min($results))[0];
        return $min;
    }

    private function getList($data, $tid = '') {
        $videos = [];
        $is_folder = strpos($tid, '/mrdg') !== false;
        foreach ($data as $k) {
            $k = pq($k);
            $a = $k->attr('href');
            $b = $k->find('h2')->text();
            $c = $k->find('span[itemprop="datePublished"]')->text();
            if ($a && $b && $c) {
                $videos[] = [
                    'vod_id' => $a . ($is_folder ? '@folder' : ''),
                    'vod_name' => str_replace("\n", ' ', $b),
                    'vod_pic' => $this->getProxyUrl() . "&url=" . $this->e64($k->find('script')->text()) . "&type=img",
                    'vod_remarks' => $c,
                    'vod_tag' => $is_folder ? 'folder' : '',
                    'style' => ['type' => 'rect', 'ratio' => 1.33]
                ];
            }
        }
        return $videos;
    }

    private function aesImg($word) {
        $key = 'f5d965df75336270';
        $iv = '97b60394abc2fbe1';
        $decrypted = openssl_decrypt($word, 'AES-128-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($decrypted === false) {
            throw new Exception("AES decryption failed");
        }
        // Remove PKCS#7 padding
        $pad_length = ord(substr($decrypted, -1));
        return substr($decrypted, 0, -$pad_length);
    }

    private function makeRequest($url, $return_content_type = false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) { return "$k: $v"; }, array_keys($this->headers), $this->headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        if ($return_content_type) {
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            return ['content' => $response, 'content_type' => $content_type];
        }
        curl_close($ch);
        return $response;
    }

    private function fetch($url) {
        $this->makeRequest($url);
    }

    private function getProxyUrl() {
        // Placeholder for proxy URL logic
        return "http://example.com/proxy";
    }

    private function getCache($key) {
        // Placeholder for cache retrieval
        return null;
    }

    private function setCache($key, $value) {
        // Placeholder for cache storage
    }

    private function log($message) {
        error_log($message);
    }
}
?>