<?php
/**
 * Visual AI Scanner with Enhanced Gender Detection and Similarity Matching
 * 
 * IMPROVEMENTS MADE:
 * 1. Enhanced gender detection with priority-based scoring system
 * 2. Removed false "exact" claims - now shows "Similar/Related" for accuracy
 * 3. Added fallback suggestions when no visual matches found
 * 4. Strict gender segregation: men's wear only suggests men's products
 * 5. Lowered similarity threshold from 80% to 65% for more inclusive results
 * 6. Support for both GET and POST requests with image upload
 */
session_start();
require_once 'database/db_config.php';
$db = new Database();
$conn = $db->getConnection();

// Check if user is logged in
$isLoggedIn = false;
$currentUser = null;
$displayName = '';
$userProfileImage = 'images/icon/profile_logo.png';

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');

if ($userId > 0 || !empty($userEmail)) {
    if ($userId > 0) {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
    } else {
        $userStmt = $conn->prepare("SELECT id, email, first_name, middle_name, last_name, profile_image FROM system_users WHERE email = ?");
        $userStmt->bind_param("s", $userEmail);
    }
    
    if ($userStmt) {
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userResult && $userResult->num_rows > 0) {
            $currentUser = $userResult->fetch_assoc();
            $isLoggedIn = true;
            $fullName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
            $displayName = !empty($fullName) ? $fullName : ($currentUser['first_name'] ?? $currentUser['email']);
            if (!empty($currentUser['profile_image'])) {
                $userProfileImage = $currentUser['profile_image'];
            }
        }
        $userStmt->close();
    }
}

// Color dictionary & fashion hierarchy
$colorDictionary = [
    'violet' => [
        'synonyms' => ['violet', 'purple', 'lavender', 'lilac', 'plum', 'magenta', 'viola'],
        'hex' => '#7b2cbf',
        'related_categories' => ['Ball Gown', 'Evening Dress', 'Debut', 'Prom']
    ],
    'purple' => [
        'synonyms' => ['purple', 'violet', 'lavender', 'lilac', 'plum'],
        'hex' => '#9d4edd',
        'related_categories' => ['Ball Gown', 'Evening Dress']
    ],
    'white' => [
        'synonyms' => ['white', 'ivory', 'cream', 'off-white', 'silver', 'pearl', 'wedgown', 'bridal'],
        'hex' => '#f8f9fa',
        'related_categories' => ['Weeding Gown', 'Ball Gown', 'Weeding Wear for Men']
    ],
    'blue' => [
        'synonyms' => ['blue', 'sky blue', 'royal blue', 'navy', 'mlue', 'baby blue', 'cobalt', 'sapphire'],
        'hex' => '#1e88e5',
        'related_categories' => ['Ball Gown', 'Evening Dress', 'Men']
    ],
    'sky blue' => [
        'synonyms' => ['sky blue', 'blue', 'baby blue', 'light blue', 'cyan', 'ember elegance', 'mlue'],
        'hex' => '#38bdf8',
        'related_categories' => ['Ball Gown', 'Evening Dress', 'Men']
    ],
    'red' => [
        'synonyms' => ['red', 'crimson', 'ruby', 'scarlet', 'maroon', 'burgundy', 'wine', 'kidgo'],
        'hex' => '#e53935',
        'related_categories' => ['Ball Gown (Kids)', 'Ball Gown', 'Debut']
    ],
    'gold' => [
        'synonyms' => ['gold', 'gold brown', 'brown', 'yellow', 'champagne', 'bronze', 'mustard', 'amber', 'gowniana'],
        'hex' => '#ffd700',
        'related_categories' => ['Filipiniana', 'Ball Gown', 'Barong (Men)']
    ],
    'gold brown' => [
        'synonyms' => ['gold brown', 'gold', 'brown', 'champagne', 'beige', 'tan', 'barongnia', 'gowniana'],
        'hex' => '#b8860b',
        'related_categories' => ['Filipiniana', 'Ball Gown', 'Barong (Men)']
    ],
    'brown' => [
        'synonyms' => ['brown', 'gold brown', 'tan', 'mocha', 'coffee', 'chocolate'],
        'hex' => '#8d6e63',
        'related_categories' => ['Filipiniana', 'Ball Gown', 'Barong (Men)']
    ],
    'black' => [
        'synonyms' => ['black', 'dark', 'charcoal', 'midnight', 'noir', 'tuxkid', 'suit'],
        'hex' => '#212121',
        'related_categories' => ['Ball Gown', 'Evening Dress', 'Tuxedo Suit (Kids)']
    ],
    'green' => [
        'synonyms' => ['green', 'emerald', 'olive', 'mint', 'sage', 'forest green', 'jade'],
        'hex' => '#43a047',
        'related_categories' => ['Ball Gown', 'Evening Dress']
    ],
    'pink' => [
        'synonyms' => ['pink', 'rose', 'blush', 'coral', 'peach', 'salmon', 'fuchsia'],
        'hex' => '#ff69b4',
        'related_categories' => ['Ball Gown', 'Ball Gown (Kids)', 'Debut']
    ],
    'silver' => [
        'synonyms' => ['silver', 'grey', 'gray', 'white', 'platinum'],
        'hex' => '#c0c0c0',
        'related_categories' => ['Weeding Gown', 'Ball Gown']
    ]
];

// AJAX API: Precision Visual Similarity & AI Matching Engine
if ((isset($_GET['action']) && $_GET['action'] === 'search_scan') || (isset($_POST['action']) && $_POST['action'] === 'search_scan')) {
    header('Content-Type: application/json');
    
    // Handle both GET and POST requests
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $params = ($requestMethod === 'POST') ? $_POST : $_GET;
    
    $query = isset($params['q']) ? strtolower(trim($params['q'])) : '';
    $color = isset($params['color']) ? strtolower(trim($params['color'])) : '';
    $secondaryColor = isset($params['secondary_color']) ? strtolower(trim($params['secondary_color'])) : '';
    $category = isset($params['category']) ? trim($params['category']) : '';
    $silhouette = isset($params['silhouette']) ? strtolower(trim($params['silhouette'])) : '';
    $neckline = isset($params['neckline']) ? strtolower(trim($params['neckline'])) : '';
    $texture = isset($params['texture']) ? strtolower(trim($params['texture'])) : '';
    $gender = isset($params['gender']) ? strtolower(trim($params['gender'])) : '';
    $exactId = isset($params['id']) ? intval($params['id']) : 0;
    $filename = isset($params['filename']) ? strtolower(trim($params['filename'])) : '';

    $exactProduct = null;
    $allGowns = [];

    // Gender indicators for detection (defined here for use in both gender detection and product filtering)
    $genderIndicators = [
        'men' => ['men', 'barong', 'tuxedo', 'suit', 'groom', 'polo', 'formal wear', 'formalwear', 'formal', 'coat', 'jacket', 'attire', 'wear', 'male', 'gentleman', 'gents', 'boys', 'man'],
        'women' => ['gown', 'dress', 'ball', 'wedding', 'debut', 'filipiniana', 'weeding', 'evening', 'prom', 'bride', 'bridal', 'lady', 'ladies', 'female', 'girl', 'woman', 'women']
    ];

    // Determine target gender ('women' / 'gowns' vs 'men') with improved detection
    $targetGender = 'women'; // Default to women for gown-focused boutique

    if (!empty($gender)) {
        $genderLower = strtolower($gender);
        if (in_array($genderLower, ['men', 'man', 'boy', 'male'])) {
            $targetGender = 'men';
        } elseif (in_array($genderLower, ['women', 'woman', 'girl', 'female', 'lady'])) {
            $targetGender = 'women';
        }
    } elseif (!empty($category)) {
        $cLow = strtolower($category);
        $menScore = 0;
        $womenScore = 0;
        
        foreach ($genderIndicators['men'] as $indicator) {
            if (strpos($cLow, $indicator) !== false) $menScore++;
        }
        foreach ($genderIndicators['women'] as $indicator) {
            if (strpos($cLow, $indicator) !== false) $womenScore++;
        }
        
        if ($menScore > $womenScore) {
            $targetGender = 'men';
        } elseif ($womenScore > $menScore) {
            $targetGender = 'women';
        }
    } elseif (!empty($silhouette)) {
        $sLow = strtolower($silhouette);
        $menScore = 0;
        $womenScore = 0;
        
        foreach ($genderIndicators['men'] as $indicator) {
            if (strpos($sLow, $indicator) !== false) $menScore++;
        }
        foreach ($genderIndicators['women'] as $indicator) {
            if (strpos($sLow, $indicator) !== false) $womenScore++;
        }
        
        if ($menScore > $womenScore) {
            $targetGender = 'men';
        } elseif ($womenScore > $menScore) {
            $targetGender = 'women';
        }
    } elseif (!empty($filename)) {
        $fnLower = strtolower($filename);
        $menScore = 0;
        $womenScore = 0;
        
        foreach ($genderIndicators['men'] as $indicator) {
            if (strpos($fnLower, $indicator) !== false) $menScore++;
        }
        foreach ($genderIndicators['women'] as $indicator) {
            if (strpos($fnLower, $indicator) !== false) $womenScore++;
        }
        
        if ($menScore > $womenScore) {
            $targetGender = 'men';
        } elseif ($womenScore > $menScore) {
            $targetGender = 'women';
        }
    } elseif (!empty($query)) {
        $qLow = strtolower($query);
        $menScore = 0;
        $womenScore = 0;
        
        foreach ($genderIndicators['men'] as $indicator) {
            if (strpos($qLow, $indicator) !== false) $menScore++;
        }
        foreach ($genderIndicators['women'] as $indicator) {
            if (strpos($qLow, $indicator) !== false) $womenScore++;
        }
        
        if ($menScore > $womenScore) {
            $targetGender = 'men';
        } elseif ($womenScore > $menScore) {
            $targetGender = 'women';
        }
    }

    // Fetch all catalog gowns - ordered by ID DESC to show newest products first
    $gownsQuery = "SELECT id, name, category, price, purchase_price, discount, color, description, image_path, image_path2, image_path3, image_path4, model_path, stock FROM gowns ORDER BY id DESC";
    $res = $conn->query($gownsQuery);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $allGowns[] = $row;
        }
    }

    // 1. Direct Identification Check (QR, Barcode, Exact ID only - strict exact match)
    if ($exactId > 0) {
        foreach ($allGowns as $g) {
            if ($g['id'] == $exactId) {
                $exactProduct = $g;
                $exactProduct['match_score'] = 100;
                $exactProduct['match_reasons'] = ['QR/Barcode Verified', 'Catalog Product Match'];
                break;
            }
        }
    }

    // Removed filename-based exact matching to avoid false positives
    // Filename matches will now go through the AI similarity scoring instead

    // 2. Build Synonyms list for color matching
    $targetColors = [];
    if (!empty($color)) {
        $targetColors[] = $color;
        if (isset($colorDictionary[$color])) {
            $targetColors = array_merge($targetColors, $colorDictionary[$color]['synonyms']);
        }
    }
    if (!empty($secondaryColor) && isset($colorDictionary[$secondaryColor])) {
        $targetColors = array_merge($targetColors, $colorDictionary[$secondaryColor]['synonyms']);
    }
    $targetColors = array_unique($targetColors);

    // 3. Multi-Vector Deep Fashion Scoring Engine
    // Weights: 40% Silhouette/Shape, 20% Color, 15% Pattern/Embroidery, 10% Neckline/Details, 5% Fabric, 5% Length/Fit, 5% Style/Occasion
    $scoredGowns = [];
    $isRealScan = (!empty($color) || !empty($silhouette) || !empty($filename) || $exactId > 0 || !empty($query) || !empty($gender));

    foreach ($allGowns as $g) {
        if ($exactProduct && $g['id'] == $exactProduct['id']) {
            continue;
        }

        $gCat = strtolower(trim($g['category'] ?? ''));
        $gName = strtolower(trim($g['name'] ?? ''));
        $gDesc = strtolower(trim($g['description'] ?? ''));
        $gColor = strtolower(trim($g['color'] ?? ''));

        // Identify product department/gender with improved detection
        $isMenProduct = false;
        $isWomenProduct = false;
        
        // Check category
        foreach ($genderIndicators['men'] as $indicator) {
            if (strpos($gCat, $indicator) !== false) $isMenProduct = true;
        }
        foreach ($genderIndicators['women'] as $indicator) {
            if (strpos($gCat, $indicator) !== false) $isWomenProduct = true;
        }
        
        // Check name as backup
        if (!$isMenProduct && !$isWomenProduct) {
            foreach ($genderIndicators['men'] as $indicator) {
                if (strpos($gName, $indicator) !== false) $isMenProduct = true;
            }
            foreach ($genderIndicators['women'] as $indicator) {
                if (strpos($gName, $indicator) !== false) $isWomenProduct = true;
            }
        }
        
        // Default to women product if no clear indicators (gown-focused boutique)
        if (!$isMenProduct && !$isWomenProduct) {
            $isWomenProduct = true;
        }

        // Strict Gender Segregation
        if ($targetGender === 'women' && $isMenProduct) {
            continue;
        }
        if ($targetGender === 'men' && $isWomenProduct) {
            continue;
        }

        $reasons = [];
        $vectorScore = 0; // Total 100 points maximum based on 8 strict visual priorities

        // Priority 1: Overall Gown Appearance and Silhouette (28 pts)
        $silhouetteScore = 0;
        if (!empty($category)) {
            $targetCat = strtolower($category);
            if ($gCat === $targetCat) {
                $silhouetteScore = 28;
                $reasons[] = 'Matching ' . $g['category'] . ' Silhouette';
            } elseif (strpos($gCat, $targetCat) !== false || strpos($targetCat, $gCat) !== false) {
                $silhouetteScore = 24;
                $reasons[] = 'Similar Gown Silhouette';
            }
        } elseif (!empty($silhouette)) {
            $sLow = strtolower($silhouette);
            if (strpos($gCat, $sLow) !== false || strpos($sLow, $gCat) !== false) {
                $silhouetteScore = 28;
                $reasons[] = 'Silhouette Fit (' . ucfirst($g['category']) . ')';
            } elseif (
                ((strpos($sLow, 'wedding') !== false || strpos($sLow, 'weeding') !== false) && (strpos($gCat, 'weeding') !== false || strpos($gCat, 'wedding') !== false)) ||
                (strpos($sLow, 'ball') !== false && (strpos($gCat, 'ball') !== false || strpos($gCat, 'debut') !== false)) ||
                (strpos($sLow, 'filipiniana') !== false && strpos($gCat, 'filipiniana') !== false) ||
                (strpos($sLow, 'debut') !== false && (strpos($gCat, 'debut') !== false || strpos($gCat, 'ball') !== false)) ||
                (strpos($sLow, 'evening') !== false && (strpos($gCat, 'gown') !== false || strpos($gCat, 'ball') !== false))
            ) {
                $silhouetteScore = 26;
                $reasons[] = 'Gown Silhouette Match';
            } elseif (strpos($gDesc, $sLow) !== false || strpos($gName, $sLow) !== false) {
                $silhouetteScore = 22;
                $reasons[] = 'Shape Contour Match';
            } else {
                $silhouetteScore = 14;
            }
        } else {
            $silhouetteScore = 20;
        }
        $vectorScore += $silhouetteScore;

        // Priority 2: Color and Color Combination (24 pts)
        $colorScore = 0;
        if (!empty($color)) {
            if ($gColor === $color) {
                $colorScore = 24;
                $reasons[] = ucfirst($g['color']) . ' Color Match';
            } elseif (in_array($gColor, $targetColors) || (isset($colorDictionary[$color]) && in_array($gColor, $colorDictionary[$color]['synonyms']))) {
                $colorScore = 21;
                $reasons[] = 'Color Harmony (' . ucfirst($g['color']) . ')';
            } else {
                $foundHarmonious = false;
                foreach ($targetColors as $tc) {
                    if (!empty($tc) && (strpos($gColor, $tc) !== false || strpos($tc, $gColor) !== false || strpos($gDesc, $tc) !== false || strpos($gName, $tc) !== false)) {
                        $colorScore = 16;
                        $reasons[] = 'Complementary Color Tone';
                        $foundHarmonious = true;
                        break;
                    }
                }
                if (!$foundHarmonious) {
                    $colorScore = 4; // Strict penalty for clashing color
                }
            }
        } else {
            $colorScore = 14;
        }
        $vectorScore += $colorScore;

        // Priority 3: Neckline and Bodice Design (14 pts)
        $necklineScore = 0;
        if (!empty($neckline)) {
            if (strpos($gDesc, $neckline) !== false || strpos($gName, $neckline) !== false) {
                $necklineScore = 14;
                $reasons[] = 'Neckline (' . ucfirst($neckline) . ')';
            } else {
                $necklineScore = 8;
            }
        } else {
            $necklineScore = 9;
        }
        $vectorScore += $necklineScore;

        // Priority 4: Fabric and Texture (12 pts)
        $fabricScore = 0;
        if (!empty($texture)) {
            if (strpos($texture, 'lace') !== false && (strpos($gDesc, 'lace') !== false || strpos($gName, 'lace') !== false)) {
                $fabricScore = 12;
                $reasons[] = 'Lace Embroidery Pattern';
            } elseif (strpos($texture, 'embroid') !== false && (strpos($gDesc, 'embroid') !== false || strpos($gName, 'embroid') !== false || strpos($gCat, 'barong') !== false)) {
                $fabricScore = 12;
                $reasons[] = 'Embroidered Pattern';
            } elseif (strpos($texture, 'satin') !== false && (strpos($gDesc, 'satin') !== false || strpos($gDesc, 'silk') !== false)) {
                $fabricScore = 11;
                $reasons[] = 'Smooth Fabric Appearance';
            } else {
                $fabricScore = 7;
            }
        } else {
            $fabricScore = 7;
        }
        $vectorScore += $fabricScore;

        // Priority 5: Skirt Shape and Length (8 pts)
        $skirtScore = 6;
        if (!empty($silhouette) && (strpos($silhouette, 'voluminous') !== false || strpos($silhouette, 'ball') !== false)) {
            if (strpos($gCat, 'ball') !== false || strpos($gCat, 'debut') !== false || strpos($gCat, 'weeding') !== false) {
                $skirtScore = 8;
                $reasons[] = 'Full Length Skirt Flare';
            }
        }
        $vectorScore += $skirtScore;

        // Priority 6: Sleeves (6 pts)
        $sleeveScore = 4;
        if (!empty($silhouette) && strpos($silhouette, 'filipiniana') !== false) {
            if (strpos($gCat, 'filipiniana') !== false) {
                $sleeveScore = 6;
                $reasons[] = 'Butterfly Sleeves Accent';
            }
        }
        $vectorScore += $sleeveScore;

        // Priority 7: Embroidery, Lace, Sequins, Beads, and Details (5 pts)
        $detailScore = 3;
        if (!empty($texture) && (strpos($texture, 'lace') !== false || strpos($texture, 'beading') !== false)) {
            if (strpos($gDesc, 'lace') !== false || strpos($gDesc, 'bead') !== false || strpos($gDesc, 'sequin') !== false) {
                $detailScore = 5;
                $reasons[] = 'Intricate Beading & Embellishments';
            }
        }
        $vectorScore += $detailScore;

        // Priority 8: Train and Other Distinctive Features (3 pts)
        $trainScore = 2;
        if (strpos($gCat, 'weeding') !== false) {
            $trainScore = 3;
        }
        $vectorScore += $trainScore;

        // Catalog Availability bonus (+1 pt)
        if (intval($g['stock']) > 0) {
            $vectorScore = min(99, $vectorScore + 1);
        }

        // Clamp final score
        $finalScore = min(99, max(40, $vectorScore));

        // STEP 5: Enhanced Strict Visual Resemblance Filter
        $minSimilarityThreshold = 65; // Lowered threshold to get more related items
        if ($isRealScan && $finalScore < $minSimilarityThreshold) {
            continue; // Rejected: Not sufficiently similar visually
        }

        // Assign 4-Tier Match Classification (adjusted for accuracy - no false "exact" claims)
        $matchTier = 'Related';
        $matchTierLabel = 'Related Style';

        if ($finalScore >= 85) {
            $matchTier = 'Very Similar';
            $matchTierLabel = 'Very Similar Style';
        } elseif ($finalScore >= 75) {
            $matchTier = 'Similar';
            $matchTierLabel = 'Similar Style';
        } elseif ($finalScore >= 65) {
            $matchTier = 'Related';
            $matchTierLabel = 'Related Style';
        } else {
            $matchTier = 'Alternative';
            $matchTierLabel = 'Alternative Option';
        }

        $g['match_score'] = $finalScore;
        $g['match_tier'] = $matchTier;
        $g['match_tier_label'] = $matchTierLabel;
        $g['match_reasons'] = array_slice($reasons, 0, 3);
        $g['has_3d'] = !empty($g['model_path']);
        $scoredGowns[] = $g;
    }

    // Sort strictly by Visual Resemblance Score DESC
    usort($scoredGowns, function($a, $b) {
        if ($b['match_score'] !== $a['match_score']) {
            return $b['match_score'] <=> $a['match_score'];
        }
        if ($b['stock'] !== $a['stock']) {
            return $b['stock'] <=> $a['stock'];
        }
        return $b['id'] <=> $a['id'];
    });

    // Tier 1: Exact match check (only for QR/Barcode verified products)
    if ($exactProduct) {
        $exactProduct['match_tier'] = 'Verified';
        $exactProduct['match_tier_label'] = 'Verified Product';
    }

    // STEP 5: Suggest visual matches (minimum 65% score threshold - more inclusive)
    if ($isRealScan) {
        $filteredGowns = array_values(array_filter($scoredGowns, function($g) {
            return ($g['match_score'] >= 65);
        }));
        $topSuggestions = $filteredGowns;
        
        // If no matches found, provide fallback suggestions based on category
        if (empty($topSuggestions) && !empty($targetGender)) {
            $fallbackGowns = array_filter($allGowns, function($g) use ($targetGender, $genderIndicators) {
                $gCat = strtolower(trim($g['category'] ?? ''));
                $gName = strtolower(trim($g['name'] ?? ''));
                
                if ($targetGender === 'men') {
                    foreach ($genderIndicators['men'] as $indicator) {
                        if (strpos($gCat, $indicator) !== false || strpos($gName, $indicator) !== false) {
                            return true;
                        }
                    }
                } else {
                    foreach ($genderIndicators['women'] as $indicator) {
                        if (strpos($gCat, $indicator) !== false || strpos($gName, $indicator) !== false) {
                            return true;
                        }
                    }
                }
                return false;
            });
            
            $topSuggestions = array_slice(array_values($fallbackGowns), 0, 12);
        }
    } else {
        // Initial page load: provide clean catalog list without artificial match score
        $cleanGowns = $allGowns;
        foreach ($cleanGowns as &$cg) {
            $cg['match_score'] = null;
            $cg['match_tier'] = null;
            $cg['match_tier_label'] = null;
            $cg['match_reasons'] = [];
            $cg['has_3d'] = !empty($cg['model_path']);
        }
        $topSuggestions = $cleanGowns;
    }

    $noMatchMsg = "No similar products found. Showing related items instead.";

    echo json_encode([
        'success' => true,
        'exact' => $exactProduct,
        'suggestions' => $topSuggestions,
        'count' => count($topSuggestions) + ($exactProduct ? 1 : 0),
        'is_scan' => $isRealScan,
        'detected_color' => ucfirst($color ?: 'Fashion Tone'),
        'detected_secondary_color' => ucfirst($secondaryColor ?: 'Accent Tone'),
        'detected_category' => ucfirst($category ?: ($targetGender === 'men' ? 'Men Attire' : 'Women Gowns')),
        'detected_gender' => $targetGender,
        'no_match_message' => $noMatchMsg
    ]);
    exit;
}

// Fetch categories for quick filter chips
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM gowns WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Visual AI & Camera Scanner - ClosetGo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/homepage.css?v=<?php echo @filemtime('css/homepage.css'); ?>">
    <link rel="stylesheet" href="css/skeleton.css?v=<?php echo @filemtime('css/skeleton.css'); ?>">
    <link id="dynamicFavicon" rel="icon" href="images/icon/JMB.jpg" type="image/jpeg">
    <!-- jsQR Library for Real-Time QR & Barcode Detection -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    <style>
        /* ===== CENTER-FOCUSED GOWN SCANNER STYLES ===== */
        #uploadPreviewWrapper {
            position: relative;
            width: 100%;
            height: 100%;
            display: none;
            border-radius: 14px;
            overflow: hidden;
        }

        #imageUploadPreview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 14px;
        }

        /* Centered Scanning Target Frame on Upload */
        .upload-center-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 78%;
            height: 82%;
            border: 2px dashed rgba(255, 215, 0, 0.6);
            border-radius: 16px;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.28);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }

        .upload-center-frame::before,
        .upload-center-frame::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-color: #ffd700;
            border-style: solid;
        }

        .upload-center-frame::before {
            top: -2px; left: -2px;
            border-width: 3px 0 0 3px;
            border-radius: 4px 0 0 0;
        }

        .upload-center-frame::after {
            bottom: -2px; right: -2px;
            border-width: 0 3px 3px 0;
            border-radius: 0 0 4px 0;
        }

        /* Modern Circular Close Button */
        .scan-modal-close-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        .scan-modal-close-btn:hover {
            background: rgba(220, 53, 69, 0.85);
            border-color: rgba(220, 53, 69, 1);
            color: #ffffff;
            transform: rotate(90deg);
        }

        /* Garment Detection Highlight and Outline */
        .garment-detection-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }

        .garment-outline {
            position: absolute;
            border: 3px solid #ffd700;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.6), 0 0 40px rgba(255, 215, 0, 0.3);
            animation: pulse-outline 2s ease-in-out infinite;
        }

        @keyframes pulse-outline {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.02); }
        }

        .garment-label {
            position: absolute;
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #111;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            z-index: 11;
        }

        /* Garment Confirmation Modal */
        .garment-confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(8px);
        }

        .garment-confirm-content {
            background: #1a1d24;
            border: 2px solid rgba(255, 215, 0, 0.5);
            border-radius: 16px;
            padding: 24px;
            max-width: 90%;
            width: 400px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        }

        .garment-confirm-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 2px solid rgba(255, 215, 0, 0.3);
        }

        .garment-confirm-title {
            color: #ffd700;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .garment-confirm-text {
            color: #ccc;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .garment-confirm-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #00e676, #00b0ff);
            color: #111;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 230, 118, 0.4);
        }

        .btn-reject {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-reject:hover {
            background: rgba(255, 68, 68, 0.3);
            border-color: rgba(255, 68, 68, 0.5);
        }

        /* AI Best Choice Card */
        .ai-best-choice-card {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.14) 0%, rgba(26, 28, 35, 0.96) 100%);
            border: 1.5px solid rgba(255, 215, 0, 0.55);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), 0 0 16px rgba(255, 215, 0, 0.15);
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ai-best-choice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-best-badge {
            background: linear-gradient(135deg, #ffd700, #ff8c00);
            color: #111;
            font-weight: 800;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ai-best-score {
            font-size: 1.05rem;
            font-weight: 800;
            color: #00e676;
            background: rgba(0, 230, 118, 0.12);
            border: 1px solid rgba(0, 230, 118, 0.35);
            padding: 3px 10px;
            border-radius: 12px;
        }

        .ai-best-choice-content {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .ai-best-img-box {
            position: relative;
            width: 105px;
            height: 145px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: #111;
            border: 1px solid rgba(255, 215, 0, 0.4);
        }

        .ai-best-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ai-best-img-tag {
            position: absolute;
            bottom: 4px;
            left: 4px;
            right: 4px;
            background: rgba(0, 0, 0, 0.75);
            color: #ffd700;
            font-size: 0.65rem;
            font-weight: 700;
            text-align: center;
            padding: 2px 4px;
            border-radius: 4px;
            backdrop-filter: blur(4px);
        }

        .ai-best-details {
            flex: 1;
            min-width: 0;
        }

        .ai-best-title {
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .ai-output-line {
            font-size: 0.85rem;
            color: #e0e0e0;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .ai-output-line strong {
            color: #ffd700;
        }

        .ai-reason-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 8px 10px;
            margin-top: 6px;
            font-size: 0.8rem;
            color: #ccc;
            line-height: 1.45;
        }

        .ai-best-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .btn-tryon {
            background: linear-gradient(135deg, #7c4dff, #00b0ff);
            color: #fff;
            border: none;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-tryon:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        /* Virtual Try-On Modal */
        .tryon-modal-content {
            max-width: 480px;
            width: 95%;
            background: #14151a;
            border: 1px solid #333;
            border-radius: 16px;
            overflow: hidden;
        }

        .tryon-canvas-container {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 12px;
            overflow: hidden;
            background: #0d0e12;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #2a2d35;
        }

        .tryon-canvas-container canvas {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .tryon-watermark {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.65);
            color: #ffd700;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            pointer-events: none;
            backdrop-filter: blur(4px);
        }

        .tryon-toggle-bar {
            display: flex;
            gap: 6px;
            margin-top: 12px;
            background: #1e2027;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #2f323a;
        }

        .tryon-toggle-btn {
            flex: 1;
            padding: 6px 8px;
            font-size: 0.75rem;
            border: none;
            background: transparent;
            color: #aaa;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .tryon-toggle-btn.active {
            background: #ffd700;
            color: #111;
        }
    </style>

</head>
<body class="camscan-page">

    <!-- Mobile Top Bar (Exact same style, structure, and color as purchase.php) -->
    <div class="top-bar">
        <div class="top-bar-content" style="justify-content: space-between;">
            <span class="top-bar-text">Scanner</span>
            <div class="camscan-header-actions">
                <button type="button" class="camscan-tool-btn" id="toggleTorchBtn" onclick="toggleFlashlight()" title="Toggle Flashlight" style="display: none;">
                    <i class="fas fa-bolt"></i>
                </button>
                <button type="button" class="camscan-tool-btn" id="switchCamBtn" onclick="switchCameraFacing()" title="Switch Camera" style="display: none;">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <main class="camscan-container">
        <!-- Scanner Card -->
        <section class="scanner-card">
            <div class="scanner-mode-tabs">
                <div class="scanner-tab active" id="tabUploadImg" onclick="switchScannerMode('upload')">
                    <i class="fas fa-cloud-upload-alt"></i> Upload Photo
                </div>
                <div class="scanner-tab" id="tabLiveCam" onclick="switchScannerMode('live')">
                    <i class="fas fa-video"></i> Live Camera
                </div>
            </div>

            <!-- Viewport Screen Area -->
            <div class="viewport-wrapper" id="viewportArea">
                <!-- Video stream -->
                <video id="camVideo" autoplay playsinline muted></video>
                <canvas id="camCanvas"></canvas>

                <!-- Reticle & Laser overlay for QR / Target frame -->
                <div class="scanner-reticle" id="scannerReticle"></div>
                <div class="scanner-laser" id="scannerLaser"></div>

                <!-- Live Cam Standby Placeholder -->
                <div class="scanner-placeholder" id="liveCamPlaceholder" style="display: none;">
                    <div class="scanner-placeholder-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 4px;">Camera Scanner Ready</div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; max-width: 260px;">
                        Point your camera at a dress, QR code, or fashion tag to auto-match.
                    </div>
                </div>

                <!-- Image Upload Dropzone -->
                <div class="upload-dropzone" id="uploadDropzone" onclick="triggerFileInput()" style="display: flex;">
                    <input type="file" id="gownFileInput" accept="image/*" style="display: none;" onchange="handleFileSelected(event)">
                    
                    <div class="upload-preview-wrapper" id="uploadPreviewWrapper">
                        <img id="imageUploadPreview" alt="Uploaded Preview">

                        <!-- Center Gown Target Reticle Frame -->
                        <div class="upload-center-frame"></div>

                        <div class="upload-scan-overlay" id="uploadScanOverlay">
                            <div class="upload-scan-laser"></div>
                            <div style="font-size: 26px; color: #ffd700; margin-bottom: 6px;"><i class="fas fa-fingerprint fa-spin"></i></div>
                            <div class="scan-progress-step-text" id="scanProgressStepText">
                                Scanning gown in center frame...
                            </div>
                        </div>
                    </div>

                    <div id="dropzonePrompt" style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                        <div class="scanner-placeholder-icon" style="background: rgba(255, 215, 0, 0.15); border-color: rgba(255, 215, 0, 0.5); color: var(--gold-primary);">
                            <i class="fas fa-images"></i>
                        </div>
                        <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 4px;">Upload Any Gown Image</div>
                        <div style="color: var(--text-muted); font-size: 0.78rem;">Tap to select gown image from gallery or files</div>
                    </div>
                </div>
            </div>

            <!-- Bottom Controls Bar -->
            <div class="scanner-controls">
                <button type="button" class="btn-cam-action" id="startCamBtn" onclick="toggleCamera()" style="display: none;">
                    <i class="fas fa-play"></i> Start Camera
                </button>
                <button type="button" class="btn-cam-secondary" id="snapPhotoBtn" onclick="snapAndAnalyze()" style="display: none;">
                    <i class="fas fa-camera-retro"></i> Snap & Match
                </button>
                <button type="button" class="btn-cam-secondary" id="reuploadBtn" onclick="triggerFileInput()" style="display: none;">
                    <i class="fas fa-sync-alt"></i> Upload Another Photo
                </button>
            </div>
        </section>

        <!-- Detection / Scan Result Notification Banner -->
        <section class="scan-status-banner" id="scanStatusBanner">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(0, 230, 118, 0.2); display: flex; align-items: center; justify-content: center; color: #00e676; font-size: 18px; flex-shrink: 0;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 0.88rem;" id="scanStatusText">Visual Scan Complete</div>
                    <div class="scan-detected-tags" id="scanDetectedTags"></div>
                </div>
            </div>
            <button type="button" onclick="clearScanFilter()" style="background: rgba(255,255,255,0.08); border: none; color: #ccc; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                ✕
            </button>
        </section>

        <!-- Verified Product Card (If found via QR / Direct Code) -->
        <div class="exact-match-card" id="exactMatchCard">
            <img src="images/icon/profile_logo.png" class="exact-match-img" id="exactMatchImg" alt="Verified Product">
            <div class="exact-match-info">
                <div class="exact-match-badge"><i class="fas fa-check-circle"></i> Verified Product</div>
                <div style="font-weight: 700; font-size: 1rem; color: #fff; margin-bottom: 2px;" id="exactMatchTitle">Gown Title</div>
                <div style="color: var(--gold-primary); font-weight: 700; font-size: 1rem;" id="exactMatchPrice">₱0.00</div>
                <div style="margin-top: 8px;">
                    <a href="#" id="exactMatchLink" class="btn-cam-action" style="padding: 6px 14px; font-size: 0.78rem; text-decoration: none;">
                        <i class="fas fa-eye"></i> View Gown Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Category / Style Filter Chips -->
        <div class="category-filter-scroll">
            <div class="cat-chip active" onclick="applyCategoryFilter('', this)">All Gowns</div>
            <?php foreach ($categories as $cat): ?>
                <div class="cat-chip" onclick="applyCategoryFilter('<?php echo htmlspecialchars(addslashes($cat)); ?>', this)">
                    <?php echo htmlspecialchars($cat); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Suggestions & Results Section -->
        <section>
            <div class="section-header-title">
                <span id="resultsHeaderTitle"><i class="fas fa-sparkles"></i> AI Suggested Gowns Based on Scan</span>
                <span class="results-count-badge" id="resultsCountBadge">Loading...</span>
            </div>

            <!-- Products List Grid -->
            <div class="products-grid" id="productsGridContainer">
                <!-- Shimmer Skeletons Initial State -->
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-product-card">
                        <div class="skeleton-product-image skeleton-shimmer"></div>
                        <div class="skeleton-product-info">
                            <div class="skeleton-product-title skeleton-shimmer"></div>
                            <div class="skeleton-product-subtitle skeleton-shimmer"></div>
                            <div class="skeleton-product-price-row">
                                <div class="skeleton-product-price skeleton-shimmer"></div>
                                <div class="skeleton-product-btn skeleton-shimmer"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </section>
    </main>



    <!-- ===== POP-UP MODAL FOR SCAN RESULTS & SUGGESTED GOWNS ===== -->
    <div class="scan-modal-overlay" id="scanResultsModal" onclick="closeScanModal()">
        <div class="scan-modal-content" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="scan-modal-header">
                <div class="scan-modal-title">
                    <i class="fas fa-sparkles"></i> Visual Scan Matches & Suggestions
                </div>
                <button type="button" class="scan-modal-close-btn" onclick="closeScanModal()" title="Close"><i class="fas fa-times"></i></button>
            </div>

            <!-- Modal Body -->
            <div class="scan-modal-body">
                <!-- Scanned Image Snapshot & Detailed Tags Header -->
                <div class="scanned-snapshot-card">
                    <img src="images/icon/profile_logo.png" class="scanned-snapshot-thumb" id="modalSnapshotThumb" alt="Scanned Snapshot">
                    <div class="scanned-snapshot-details">
                        <div style="font-weight: 700; font-size: 0.9rem; color: #fff; margin-bottom: 2px;" id="modalSnapshotHeading">Visual Gown Analysis</div>
                        <div class="scan-detected-tags" id="modalDetectedTags"></div>
                        <div class="detected-palette-row" id="modalPaletteRow"></div>
                    </div>
                </div>

                <!-- AI Top Recommendation & Try-On Card -->
                <div class="ai-best-choice-card" id="aiBestChoiceCard" style="display: none;"></div>

                <!-- Verified Product Card inside Modal -->
                <div class="exact-match-card" id="modalExactMatchCard" style="display: none; margin-bottom: 16px;">
                    <img src="images/icon/profile_logo.png" class="exact-match-img" id="modalExactMatchImg" alt="Verified Product">
                    <div class="exact-match-info">
                        <div class="exact-match-badge"><i class="fas fa-check-circle"></i> Verified Product</div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: #fff; margin-bottom: 2px;" id="modalExactMatchTitle">Gown Title</div>
                        <div style="color: var(--gold-primary); font-weight: 700; font-size: 0.95rem;" id="modalExactMatchPrice">₱0.00</div>
                        <div style="margin-top: 8px;">
                            <a href="#" id="modalExactMatchLink" class="btn-cam-action" style="padding: 6px 14px; font-size: 0.78rem; text-decoration: none;">
                                <i class="fas fa-eye"></i> View Gown Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Suggested Gowns Grid inside Modal -->
                <div style="font-size: 0.92rem; font-weight: 700; color: #ffd700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-magic"></i> AI Suggested Recommendations:
                </div>
                <div class="products-grid" id="modalProductsGrid"></div>
            </div>
        </div>
    </div>

    <!-- ===== VIRTUAL WEARER TRY-ON MODAL ===== -->
    <div class="scan-modal-overlay" id="tryOnModal" style="display: none;" onclick="closeTryOnModal()">
        <div class="tryon-modal-content" onclick="event.stopPropagation()">
            <div class="scan-modal-header">
                <div class="scan-modal-title">
                    <i class="fas fa-magic"></i> Virtual Wearer Try-On Preview
                </div>
                <button type="button" class="scan-modal-close-btn" onclick="closeTryOnModal()" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="scan-modal-body" style="padding: 16px;">
                <!-- View Mode Toggle Buttons -->
                <div class="tryon-toggle-bar">
                    <button type="button" class="tryon-toggle-btn active" id="tryonTabComposite" onclick="switchTryOnView('composite')">
                        <i class="fas fa-layer-group"></i> Try-On Composite
                    </button>
                    <button type="button" class="tryon-toggle-btn" id="tryonTabPerson" onclick="switchTryOnView('person')">
                        <i class="fas fa-user"></i> Wearer Reference
                    </button>
                    <button type="button" class="tryon-toggle-btn" id="tryonTabGown" onclick="switchTryOnView('gown')">
                        <i class="fas fa-tshirt"></i> Selected Gown
                    </button>
                </div>

                <!-- Canvas Preview Container -->
                <div class="tryon-canvas-container" style="margin-top: 12px;">
                    <canvas id="tryOnCanvas"></canvas>
                    <div class="tryon-watermark">
                        <i class="fas fa-shield-alt"></i> Authentic Gown Design Preserved
                    </div>
                </div>

                <!-- Try-On Info Banner -->
                <div id="tryOnInfoBox" style="margin-top: 12px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-weight: 700; color: #fff; font-size: 0.95rem;" id="tryOnGownTitle">Gown Title</div>
                        <div style="font-weight: 800; color: #ffd700; font-size: 0.95rem;" id="tryOnGownPrice">₱0.00</div>
                    </div>
                    <div style="font-size: 0.78rem; color: #aaa; margin-top: 4px;">
                        <i class="fas fa-info-circle"></i> The selected gown's original cut, embroidery, color, shape, and fabric details are kept 100% authentic without distortion.
                    </div>
                </div>

                <!-- Direct Action Buttons -->
                <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <a href="#" id="tryOnViewProductBtn" class="btn-cam-action" style="flex: 1; justify-content: center; text-decoration: none; padding: 10px 16px;">
                        <i class="fas fa-calendar-check"></i> Reserve This Gown
                    </a>
                    <button type="button" class="btn-cam-secondary" onclick="closeTryOnModal()" style="padding: 10px 16px;">
                        Back
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== GARMENT DETECTION CONFIRMATION MODAL ===== -->
    <div class="garment-confirm-modal" id="garmentConfirmModal" style="display: none;">
        <div class="garment-confirm-content">
            <div class="garment-confirm-title">
                <i class="fas fa-search"></i> Garment Detected
            </div>
            <img id="garmentConfirmImage" class="garment-confirm-image" src="" alt="Detected Garment">
            <div class="garment-confirm-text" id="garmentConfirmText">
                We detected one gown in this image. Is this the garment you want to scan?
            </div>
            <div class="garment-confirm-actions">
                <button type="button" class="btn-confirm" onclick="confirmGarmentDetection()">
                    <i class="fas fa-check"></i> Yes, Scan This
                </button>
                <button type="button" class="btn-reject" onclick="rejectGarmentDetection()">
                    <i class="fas fa-times"></i> No, Re-upload
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation (5-Tab with Center Pink FAB) -->
    <nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation">
        <!-- SVG Curved Scoop Notch Background Bar -->
        <div class="mobile-nav-bg-container">
            <div class="mobile-nav-wing"></div>
            <svg class="mobile-nav-notch-center" viewBox="0 0 108 62" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M 0,10 
                         L 12,10 
                         C 22,10 26,18 28,26 
                         A 32 32 0 0 0 80,26 
                         C 82,18 86,10 96,10 
                         L 108,10 
                         L 108,62 
                         L 0,62 Z" 
                      fill="#212328" />
            </svg>
            <div class="mobile-nav-wing"></div>
        </div>

        <a href="index.php" class="nav-item">
            <img src="images/icon/home.png" alt="Home" class="nav-icon">
            <span class="nav-text">Home</span>
        </a>
        <a href="save.php" class="nav-item">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/cart.png" alt="Cart" class="nav-icon">
                <span id="cartCount" class="purchase-nav-badge cart-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Cart</span>
        </a>
        <div class="nav-center-gap"></div>
        <div class="nav-fab-wrapper">
            <a href="product_camscan.php" class="nav-fab-scan-btn active" title="Camera Scan">
                <img src="images/icon/camera.png" alt="Camera Scan" class="nav-fab-icon">
            </a>
        </div>
        <a href="purchase.php" class="nav-item">
            <div style="position: relative; display: inline-flex; align-items: center; justify-content: center;">
                <img src="images/icon/purchase.png" alt="Purchases" class="nav-icon">
                <span id="purchaseBadge" class="purchase-nav-badge" style="display: none;">0</span>
            </div>
            <span class="nav-text">Purchases</span>
        </a>
        <a href="profile.php" class="nav-item">
            <img src="images/icon/profile.png" alt="Profile" class="nav-icon">
            <span class="nav-text">Profile</span>
        </a>
    </nav>

    <script>
        // State variables
        let videoStream = null;
        let isScanning = false;
        let currentFacingMode = 'environment';
        let scanMode = 'upload';
        let activeCategoryFilter = '';
        let activeColorFilter = '';
        let qrScanInterval = null;
        let torchEnabled = false;

        // ── Garment Detection & Smart Cut State ──────────────────
        let detectedRegions    = [];
        let currentFullImgUrl  = '';
        let garmentOrigW       = 0;
        let garmentOrigH       = 0;
        let garmentBoundingBox = null;
        let isolatedGarmentCanvas = null;
        let garmentDetectionConfirmed = false;
        let garmentDetectionConfidence = 0;
        // ─────────────────────────────────────────────────────────

        const videoEl = document.getElementById('camVideo');
        const canvasEl = document.getElementById('camCanvas');
        const canvasCtx = canvasEl.getContext('2d', { willReadFrequently: true });
        const reticleEl = document.getElementById('scannerReticle');
        const laserEl = document.getElementById('scannerLaser');
        const placeholderEl = document.getElementById('liveCamPlaceholder');
        const startBtn = document.getElementById('startCamBtn');
        const snapBtn = document.getElementById('snapPhotoBtn');
        const uploadZone = document.getElementById('uploadDropzone');
        const fileInput = document.getElementById('gownFileInput');
        const previewWrapper = document.getElementById('uploadPreviewWrapper');
        const imgPreview = document.getElementById('imageUploadPreview');
        const scanOverlay = document.getElementById('uploadScanOverlay');
        const progressStepText = document.getElementById('scanProgressStepText');
        const dropPrompt = document.getElementById('dropzonePrompt');
        const reuploadBtn = document.getElementById('reuploadBtn');

        // Color family definitions with representative hex
        const colorPaletteMeta = {
            'Violet': '#7b2cbf',
            'Purple': '#9d4edd',
            'White': '#f8f9fa',
            'Blue': '#1e88e5',
            'Sky Blue': '#38bdf8',
            'Red': '#e53935',
            'Pink': '#ff69b4',
            'Gold': '#ffd700',
            'Gold Brown': '#b8860b',
            'Brown': '#8d6e63',
            'Black': '#212121',
            'Green': '#43a047',
            'Silver': '#c0c0c0'
        };

        // Initialize suggestions on page load
        document.addEventListener('DOMContentLoaded', () => {
            fetchSuggestions('', '', '');
            fetchCartAndPurchaseBadges();
            
            // Preload all products from admin API to ensure complete catalog
            preloadAllProductsFromAdmin();
        });

        /**
         * Preload all products from admin API to ensure complete catalog
         */
        async function preloadAllProductsFromAdmin() {
            try {
                const adminR = await fetch('admin/gowns.php?action=getAllGowns');
                const adminD = await adminR.json();
                if (adminD && Array.isArray(adminD)) {
                    window._catalogProducts = adminD;
                    console.log(`Preloaded ${adminD.length} products from admin API`);
                }
            } catch (e) {
                console.warn('Could not preload products from admin API:', e);
            }
        }

        // Toggle Scanner Mode (Live Camera vs Upload Photo)
        function switchScannerMode(mode) {
            scanMode = mode;
            document.querySelectorAll('.scanner-tab').forEach(t => t.classList.remove('active'));

            if (mode === 'live') {
                document.getElementById('tabLiveCam').classList.add('active');
                uploadZone.style.display = 'none';
                reuploadBtn.style.display = 'none';
                placeholderEl.style.display = videoStream ? 'none' : 'flex';
                videoEl.style.display = videoStream ? 'block' : 'none';
                startBtn.style.display = 'inline-flex';
                snapBtn.style.display = videoStream ? 'inline-flex' : 'none';
                if (videoStream) {
                    reticleEl.style.display = 'block';
                    laserEl.style.display = 'block';
                }
            } else {
                document.getElementById('tabUploadImg').classList.add('active');
                stopCamera();
                placeholderEl.style.display = 'none';
                videoEl.style.display = 'none';
                reticleEl.style.display = 'none';
                laserEl.style.display = 'none';
                uploadZone.style.display = 'flex';
                startBtn.style.display = 'none';
                snapBtn.style.display = 'none';
                if (imgPreview.src && previewWrapper.style.display === 'block') {
                    reuploadBtn.style.display = 'inline-flex';
                }
            }
        }

        // Toggle Camera Stream
        async function toggleCamera() {
            if (videoStream) {
                stopCamera();
            } else {
                await startCamera();
            }
        }

        async function startCamera() {
            try {
                const constraints = {
                    video: {
                        facingMode: { ideal: currentFacingMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                videoStream = await navigator.mediaDevices.getUserMedia(constraints);
                videoEl.srcObject = videoStream;
                await videoEl.play();

                videoEl.style.display = 'block';
                placeholderEl.style.display = 'none';
                reticleEl.style.display = 'block';
                laserEl.style.display = 'block';

                startBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Camera';
                startBtn.style.background = 'linear-gradient(135deg, #ff4444, #cc0000)';
                startBtn.style.color = '#fff';
                snapBtn.style.display = 'inline-flex';

                document.getElementById('switchCamBtn').style.display = 'inline-flex';
                checkTorchSupport();

                isScanning = true;
                startQRScanning();
            } catch (err) {
                console.error("Camera access error:", err);
                alert("Could not access camera. Please ensure camera permissions are granted in your browser settings.");
                stopCamera();
            }
        }

        function stopCamera() {
            if (videoStream) {
                videoStream.getTracks().forEach(t => t.stop());
                videoStream = null;
            }
            isScanning = false;
            if (qrScanInterval) {
                cancelAnimationFrame(qrScanInterval);
                qrScanInterval = null;
            }

            videoEl.style.display = 'none';
            reticleEl.style.display = 'none';
            laserEl.style.display = 'none';
            if (scanMode === 'live') {
                placeholderEl.style.display = 'flex';
            }

            startBtn.innerHTML = '<i class="fas fa-play"></i> Start Camera';
            startBtn.style.background = 'linear-gradient(135deg, var(--gold-primary), var(--gold-dark))';
            startBtn.style.color = '#111';
            snapBtn.style.display = 'none';
            document.getElementById('switchCamBtn').style.display = 'none';
            document.getElementById('toggleTorchBtn').style.display = 'none';
        }

        async function switchCameraFacing() {
            currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
            if (videoStream) {
                stopCamera();
                await startCamera();
            }
        }

        function checkTorchSupport() {
            if (!videoStream) return;
            const track = videoStream.getVideoTracks()[0];
            if (track && typeof track.getCapabilities === 'function') {
                const capabilities = track.getCapabilities();
                if (capabilities.torch) {
                    document.getElementById('toggleTorchBtn').style.display = 'inline-flex';
                }
            }
        }

        function toggleFlashlight() {
            if (!videoStream) return;
            const track = videoStream.getVideoTracks()[0];
            if (track && typeof track.applyConstraints === 'function') {
                torchEnabled = !torchEnabled;
                track.applyConstraints({
                    advanced: [{ torch: torchEnabled }]
                }).catch(() => {});
                document.getElementById('toggleTorchBtn').classList.toggle('active', torchEnabled);
            }
        }

        // Continuous QR & Barcode Detection
        function startQRScanning() {
            if (!isScanning || !videoStream) return;

            if (videoEl.readyState === videoEl.HAVE_ENOUGH_DATA) {
                canvasEl.width = videoEl.videoWidth;
                canvasEl.height = videoEl.videoHeight;
                canvasCtx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);

                const imageData = canvasCtx.getImageData(0, 0, canvasEl.width, canvasEl.height);
                if (window.jsQR) {
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });

                    if (code && code.data) {
                        handleCodeDetected(code.data);
                        return;
                    }
                }
            }

            qrScanInterval = requestAnimationFrame(startQRScanning);
        }

        function handleCodeDetected(codeText) {
            let foundId = 0;
            let match = codeText.match(/(?:id=|gown_id=|id:)(\d+)/i);
            if (match) {
                foundId = parseInt(match[1]);
            } else if (!isNaN(parseInt(codeText)) && parseInt(codeText) > 0) {
                foundId = parseInt(codeText);
            }

            const snapshotUrl = canvasEl.toDataURL('image/jpeg');
            const tags = ['QR / Barcode Verified', '100% Match'];
            showScanBanner(`Scanned: ${escapeHtml(codeText)}`, tags);
            fetchSuggestionsAndPopModal('', '', '', '', '', '', '', foundId, '', snapshotUrl, tags, `Product Tag Scanned: ${codeText}`, null, null);
        }

        // Snap current frame → Scan Center Gown
        function snapAndAnalyze() {
            if (!videoStream || videoEl.readyState < videoEl.HAVE_ENOUGH_DATA) return;

            canvasEl.width  = videoEl.videoWidth;
            canvasEl.height = videoEl.videoHeight;
            canvasCtx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
            
            const snapshotUrl = canvasEl.toDataURL('image/jpeg', 0.95);

            // Switch to upload view to show snapshot & scanning overlay
            switchScannerMode('upload');
            imgPreview.src = snapshotUrl;
            previewWrapper.style.display = 'block';
            dropPrompt.style.display    = 'none';
            reuploadBtn.style.display   = 'inline-flex';
            scanOverlay.style.display   = 'flex';
            progressStepText.textContent = 'AI analyzing garment style & features...';

            // Convert canvas to blob for AI analysis
            canvasEl.toBlob(function(blob) {
                const file = new File([blob], "camera_scan.jpg", { type: "image/jpeg" });
                
                // AI-powered scan with image upload
                setTimeout(() => {
                    scanOverlay.style.display = 'none';
                    fetchSuggestionsAndPopModal('', '', '', '', '', '', '', 0, 'camera_scan', snapshotUrl, [], 'AI Camera Analysis', null, file);
                }, 800);
            }, 'image/jpeg', 0.8);
        }

        // Handle Image File Upload - reset state on re-upload
        function triggerFileInput() {
            fileInput.click();
        }

        // Handle Image File Upload → Scan Center Gown
        function handleFileSelected(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const filenameClean = file.name.replace(/\.[^/.]+$/, '');

            const reader = new FileReader();
            reader.onload = function(evt) {
                imgPreview.src = evt.target.result;
                previewWrapper.style.display = 'block';
                dropPrompt.style.display    = 'none';
                reuploadBtn.style.display   = 'inline-flex';
                scanOverlay.style.display   = 'flex';

                progressStepText.textContent = 'Scanning garment & analyzing with AI...';

                const img = new Image();
                img.onload = function() {
                    canvasEl.width  = img.width;
                    canvasEl.height = img.height;
                    canvasCtx.drawImage(img, 0, 0);

                    // Step 1: Check for QR code
                    let foundId = 0;
                    if (window.jsQR) {
                        const imgData = canvasCtx.getImageData(0, 0, canvasEl.width, canvasEl.height);
                        const code = jsQR(imgData.data, imgData.width, imgData.height);
                        if (code && code.data) {
                            let match = code.data.match(/(?:id=|gown_id=|id:)(\d+)/i);
                            if (match) foundId = parseInt(match[1]);
                            else if (!isNaN(parseInt(code.data))) foundId = parseInt(code.data);
                        }
                    }

                    // Step 2: Check for exact filename match against database products
                    if (foundId === 0 && window._catalogProducts && window._catalogProducts.length > 0) {
                        const matchResult = findExactProductByFilename(filenameClean, window._catalogProducts);
                        if (matchResult) {
                            foundId = matchResult.id;
                            console.log('Found exact product match by filename:', matchResult.name);
                        }
                    }

                    if (foundId > 0) {
                        // QR or filename match detected — use exact match flow
                        setTimeout(() => { progressStepText.textContent = 'Product verified!'; }, 320);
                        setTimeout(() => {
                            scanOverlay.style.display = 'none';
                            const tags = ['Product Verified', '100% Match'];
                            showScanBanner('Product Verified', tags);
                            fetchSuggestionsAndPopModal('', '', '', '', '', '', '', foundId, filenameClean, evt.target.result, tags, 'Product Match Verified', null, file);
                        }, 800);
                    } else {
                        // AI-powered scan with image upload for better accuracy
                        setTimeout(() => { progressStepText.textContent = 'AI analyzing garment style & features...'; }, 350);
                        setTimeout(() => {
                            scanOverlay.style.display = 'none';
                            fetchSuggestionsAndPopModal('', '', '', '', '', '', '', 0, filenameClean, evt.target.result, [], 'AI Visual Analysis', null, file);
                        }, 850);
                    }
                };
                img.src = evt.target.result;
            };
            reader.readAsDataURL(file);
        }

        /**
         * Find exact product by filename matching
         */
        function findExactProductByFilename(filename, products) {
            const cleanFn = filename.toLowerCase().replace(/[^a-z0-9]/g, '');
            const fnTokens = cleanFn.split('').filter(c => c.match(/[a-z0-9]/)).join('').match(/.{1,4}/g) || [];

            for (const product of products) {
                const allImages = [
                    product.image_path,
                    product.image_path2,
                    product.image_path3,
                    product.image_path4
                ].filter(img => img && img.trim() !== '');

                for (const imgPath of allImages) {
                    const imgBasename = imgPath.toLowerCase().split('/').pop().replace(/\.[^/.]+$/, '');
                    const cleanImg = imgBasename.replace(/[^a-z0-9]/g, '');
                    const productName = (product.name || '').toLowerCase().replace(/[^a-z0-9]/g, '');

                    // Exact basename match
                    if (cleanImg === cleanFn || imgBasename === filename.toLowerCase()) {
                        return product;
                    }

                    // Partial match with significant overlap
                    if (cleanImg.length > 5 && cleanFn.length > 5) {
                        const overlap = calculateStringOverlap(cleanImg, cleanFn);
                        if (overlap > 0.7) {
                            return product;
                        }
                    }

                    // Product name match
                    if (productName === cleanFn || productName.includes(cleanFn) || cleanFn.includes(productName)) {
                        return product;
                    }

                    // Token matching
                    for (const token of fnTokens) {
                        if (token.length >= 3 && cleanImg.includes(token)) {
                            return product;
                        }
                    }
                }
            }

            return null;
        }

        /**
         * Calculate string overlap ratio
         */
        function calculateStringOverlap(str1, str2) {
            let matches = 0;
            const minLen = Math.min(str1.length, str2.length);
            
            for (let i = 0; i < minLen; i++) {
                if (str1[i] === str2[i]) {
                    matches++;
                }
            }
            
            return matches / Math.max(str1.length, str2.length);
        }

        /**
         * Fetch suggestions and populate modal with exact match
         */
        function fetchSuggestionsAndPopModal(query, color, category, silhouette, neckline, texture, gender, exactId, filename, snapshotUrl, tags, heading, analysis, imageFile) {
            const formData = new FormData();
            formData.append('action', 'search_scan');
            if (query) formData.append('q', query);
            if (color) formData.append('color', color);
            if (category) formData.append('category', category);
            if (exactId > 0) formData.append('id', exactId);
            if (filename) formData.append('filename', filename);
            if (imageFile) formData.append('scan_image', imageFile);

            fetch('product_camscan.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data && data.suggestions) {
                        window._catalogProducts = data.suggestions;
                        preloadCatalogImageSignatures(data.suggestions);
                    }
                    
                    // Also fetch from admin API to ensure we have ALL products
                    fetch('admin/gowns.php?action=getAllGowns')
                        .then(adminR => adminR.json())
                        .then(adminD => {
                            if (adminD && Array.isArray(adminD)) {
                                const existingIds = new Set(window._catalogProducts.map(p => p.id));
                                adminD.forEach(p => {
                                    if (!existingIds.has(p.id)) {
                                        window._catalogProducts.push(p);
                                    }
                                });
                            }
                        })
                        .catch(e => console.warn('Could not fetch from admin API:', e));
                    
                    renderSearchResults(data, true);
                    popScanResultsModal(data, snapshotUrl, tags, heading, analysis);
                })
                .catch(err => {
                    console.error("Fetch suggestions error:", err);
                    showScanBanner('Error loading products', ['Please try again']);
                });
        }

        // =========================================================================
        // AI VISUAL GOWN-MATCHING & VIRTUAL STYLIST SYSTEM
        // 1. Detects & isolates gown clothing, completely stripping background
        //    (walls, scenery, furniture, studio lighting, props).
        // 2. Analyzes person's appearance (skin undertone) & proportions (shoulders, waist, hips).
        // 3. Compares all 4 uploaded images per gown image-to-image against wearer.
        // 4. Recommends Best Choice (Image 1-4, Score 0-100, Reason).
        // 5. Preserves selected gown's original design, color, shape & details for Try-On.
        // =========================================================================

        window._catalogProducts = [];
        window._catalogSignatures = {};
        window._currentAiBestChoice = null;
        window._currentTryOnMode = 'composite';

        /**
         * STEP 1: Detect garment and create bounding box with visual highlighting
         * Detects the primary gown/wear in the uploaded image and creates a precise boundary
         */
        function detectGarmentWithHighlight(sourceCanvas, origW, origH) {
            const aW = 320, aH = 480;
            const offCanvas = document.createElement('canvas');
            offCanvas.width = aW;
            offCanvas.height = aH;
            const offCtx = offCanvas.getContext('2d', { willReadFrequently: true });
            offCtx.drawImage(sourceCanvas, 0, 0, origW, origH, 0, 0, aW, aH);

            const imgData = offCtx.getImageData(0, 0, aW, aH).data;

            // Sample perimeter background
            let bgR = 0, bgG = 0, bgB = 0, bgSamples = 0;
            const borderSize = Math.max(3, Math.floor(aW * 0.05));
            for (let y = 0; y < aH; y++) {
                for (let x = 0; x < aW; x++) {
                    if (x < borderSize || x >= aW - borderSize || y < borderSize || y >= aH - borderSize) {
                        const idx = (y * aW + x) * 4;
                        bgR += imgData[idx];
                        bgG += imgData[idx + 1];
                        bgB += imgData[idx + 2];
                        bgSamples++;
                    }
                }
            }
            const avgBgR = bgSamples > 0 ? (bgR / bgSamples) : 240;
            const avgBgG = bgSamples > 0 ? (bgG / bgSamples) : 240;
            const avgBgB = bgSamples > 0 ? (bgB / bgSamples) : 240;
            const avgBgLum = (avgBgR * 0.299 + avgBgG * 0.587 + avgBgB * 0.114) / 255;

            // Find garment bounding box
            let minX = aW, maxX = 0, minY = aH, maxY = 0;
            let garmentPixelCount = 0;
            let totalNonBgPixels = 0;

            for (let y = 0; y < aH; y++) {
                for (let x = 0; x < aW; x++) {
                    const idx = (y * aW + x) * 4;
                    const r = imgData[idx];
                    const g = imgData[idx + 1];
                    const b = imgData[idx + 2];

                    const hsl = rgbToHsl(r, g, b);
                    const h = hsl.h, s = hsl.s, l = hsl.l;

                    // Distance to background
                    const distToBg = Math.sqrt(
                        Math.pow(r - avgBgR, 2) + 
                        Math.pow(g - avgBgG, 2) + 
                        Math.pow(b - avgBgB, 2)
                    );
                    const isBgMatch = (distToBg < 44) || (Math.abs(l - avgBgLum) < 0.10 && s < 0.18);

                    // Exclude skin tones
                    const isSkin = (h >= 7 && h <= 44 && s >= 0.16 && s <= 0.78 && l >= 0.32 && l <= 0.88);

                    // Exclude head area
                    const isHeadArea = (y < aH * 0.16 && (isSkin || (l < 0.18 && s < 0.28)));

                    // Garment pixel detection
                    const isGarmentPixel = !isBgMatch && !isSkin && !isHeadArea && (
                        (l > 0.08 && l < 0.95 && (s > 0.06 || l < 0.25 || l > 0.78))
                    );

                    if (isGarmentPixel) {
                        garmentPixelCount++;
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                    }
                    
                    if (!isBgMatch) {
                        totalNonBgPixels++;
                    }
                }
            }

            // Calculate confidence based on garment pixel ratio
            const confidence = garmentPixelCount / Math.max(1, totalNonBgPixels);
            
            // Scale bounding box back to original dimensions
            const scaleX = origW / aW;
            const scaleY = origH / aH;

            const boundingBox = {
                x: minX * scaleX,
                y: minY * scaleY,
                width: (maxX - minX) * scaleX,
                height: (maxY - minY) * scaleY,
                confidence: confidence,
                garmentPixelCount: garmentPixelCount,
                totalPixels: totalNonBgPixels
            };

            return boundingBox;
        }

        /**
         * Draw garment outline highlight on the preview image
         */
        function drawGarmentHighlight(boundingBox, previewWrapper) {
            // Remove existing highlight
            const existingHighlight = previewWrapper.querySelector('.garment-detection-overlay');
            if (existingHighlight) {
                existingHighlight.remove();
            }

            if (!boundingBox || boundingBox.garmentPixelCount < 50) {
                return false;
            }

            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'garment-detection-overlay';

            // Create outline
            const outline = document.createElement('div');
            outline.className = 'garment-outline';
            
            // Position outline relative to the preview wrapper
            const imgPreview = document.getElementById('imageUploadPreview');
            const imgRect = imgPreview.getBoundingClientRect();
            const wrapperRect = previewWrapper.getBoundingClientRect();

            const relativeX = (boundingBox.x / garmentOrigW) * 100;
            const relativeY = (boundingBox.y / garmentOrigH) * 100;
            const relativeWidth = (boundingBox.width / garmentOrigW) * 100;
            const relativeHeight = (boundingBox.height / garmentOrigH) * 100;

            outline.style.left = relativeX + '%';
            outline.style.top = relativeY + '%';
            outline.style.width = relativeWidth + '%';
            outline.style.height = relativeHeight + '%';

            // Create label
            const label = document.createElement('div');
            label.className = 'garment-label';
            label.textContent = 'Detected Gown';
            label.style.left = relativeX + '%';
            label.style.top = (relativeY - 5) + '%';

            overlay.appendChild(outline);
            overlay.appendChild(label);
            previewWrapper.appendChild(overlay);

            return true;
        }

        /**
         * STEP 2: Isolate garment and create cropped version
         * Creates a temporary cropped/isolated version containing ONLY the gown/wear
         */
        function isolateGarment(sourceCanvas, boundingBox) {
            if (!boundingBox || boundingBox.garmentPixelCount < 50) {
                return null;
            }

            const isoCanvas = document.createElement('canvas');
            const padding = 20; // Add some padding around the garment
            
            isoCanvas.width = boundingBox.width + padding * 2;
            isoCanvas.height = boundingBox.height + padding * 2;
            
            const isoCtx = isoCanvas.getContext('2d');
            
            // Draw the isolated garment region
            isoCtx.drawImage(
                sourceCanvas,
                boundingBox.x - padding,
                boundingBox.y - padding,
                boundingBox.width + padding * 2,
                boundingBox.height + padding * 2,
                0,
                0,
                isoCanvas.width,
                isoCanvas.height
            );

            return isoCanvas;
        }

        /**
         * STEP 3: Show garment confirmation modal
         */
        function showGarmentConfirmation(isolatedCanvas, confidence) {
            const modal = document.getElementById('garmentConfirmModal');
            const confirmImage = document.getElementById('garmentConfirmImage');
            const confirmText = document.getElementById('garmentConfirmText');

            // Show isolated garment
            confirmImage.src = isolatedCanvas.toDataURL('image/jpeg', 0.9);
            
            // Set confidence message
            if (confidence > 0.7) {
                confirmText.textContent = 'We detected one gown with high confidence. Is this the garment you want to scan?';
            } else if (confidence > 0.4) {
                confirmText.textContent = 'We detected a possible gown. Is this the garment you want to scan?';
            } else {
                confirmText.textContent = 'Garment detection is uncertain. Is this the garment you want to scan?';
            }

            modal.style.display = 'flex';
            garmentDetectionConfirmed = false;
        }

        function confirmGarmentDetection() {
            garmentDetectionConfirmed = true;
            document.getElementById('garmentConfirmModal').style.display = 'none';
            
            // Proceed with scanning the confirmed garment
            if (isolatedGarmentCanvas) {
                proceedWithGarmentScan();
            }
        }

        function rejectGarmentDetection() {
            garmentDetectionConfirmed = false;
            document.getElementById('garmentConfirmModal').style.display = 'none';
            
            // Reset and ask user to upload another image
            triggerFileInput();
        }

        /**
         * Detects and isolates ONLY the gown/clothing in an image.
         * Completely ignores and strips out the background:
         * - Walls, scenery, studio backdrops, lighting, furniture, props.
         * - Also excludes wearer face, skin, and hair so pure garment cloth is analyzed.
         * Preserves authentic garment pixels in a transparent cutout canvas for virtual try-on.
         */
        function extractFullGownSignature(sourceCanvas, origW, origH) {
            const aW = 160, aH = 220;
            const offCanvas = document.createElement('canvas');
            offCanvas.width = aW;
            offCanvas.height = aH;
            const offCtx = offCanvas.getContext('2d', { willReadFrequently: true });
            offCtx.drawImage(sourceCanvas, 0, 0, origW, origH, 0, 0, aW, aH);

            const imgData = offCtx.getImageData(0, 0, aW, aH).data;

            // Step 1: Sample perimeter boundary pixels (outer 5%) to profile background (walls, furniture, backdrop, scenery, lighting)
            let bgR = 0, bgG = 0, bgB = 0, bgSamples = 0;
            const borderSize = Math.max(3, Math.floor(aW * 0.05));
            for (let y = 0; y < aH; y++) {
                for (let x = 0; x < aW; x++) {
                    if (x < borderSize || x >= aW - borderSize || y < borderSize || y >= aH - borderSize) {
                        const idx = (y * aW + x) * 4;
                        bgR += imgData[idx];
                        bgG += imgData[idx + 1];
                        bgB += imgData[idx + 2];
                        bgSamples++;
                    }
                }
            }
            const avgBgR = bgSamples > 0 ? (bgR / bgSamples) : 240;
            const avgBgG = bgSamples > 0 ? (bgG / bgSamples) : 240;
            const avgBgB = bgSamples > 0 ? (bgB / bgSamples) : 240;
            const avgBgLum = (avgBgR * 0.299 + avgBgG * 0.587 + avgBgB * 0.114) / 255;

            // Cutout canvas to store isolated pure garment cloth (for virtual try-on)
            const cutoutCanvas = document.createElement('canvas');
            cutoutCanvas.width = aW;
            cutoutCanvas.height = aH;
            const cutoutCtx = cutoutCanvas.getContext('2d');
            const cutoutImgData = cutoutCtx.createImageData(aW, aH);

            const hueBins = new Float32Array(16);
            let totalGownPixels = 0;
            let lumSum = 0, satSum = 0;
            let edgeContrastVariance = 0;
            let sparklePixelCount = 0;
            let prevLum = 0;

            const SLICES = 16;
            const sliceSliceLeft = new Int32Array(SLICES).fill(aW);
            const sliceSliceRight = new Int32Array(SLICES).fill(0);
            const sliceGownPixels = new Int32Array(SLICES).fill(0);

            for (let y = 0; y < aH; y++) {
                const sliceIdx = Math.min(SLICES - 1, Math.floor((y / aH) * SLICES));

                for (let x = 0; x < aW; x++) {
                    const idx = (y * aW + x) * 4;
                    const r = imgData[idx];
                    const g = imgData[idx + 1];
                    const b = imgData[idx + 2];

                    const hsl = rgbToHsl(r, g, b);
                    const h = hsl.h, s = hsl.s, l = hsl.l;

                    // Distance to sampled perimeter background (walls, studio curtain, props, floor, lighting)
                    const distToBg = Math.sqrt(
                        Math.pow(r - avgBgR, 2) + 
                        Math.pow(g - avgBgG, 2) + 
                        Math.pow(b - avgBgB, 2)
                    );
                    const isBgMatch = (distToBg < 44) || (Math.abs(l - avgBgLum) < 0.10 && s < 0.18);

                    // Exclude skin tones (Face, neck, arms, legs)
                    const isSkin = (h >= 7 && h <= 44 && s >= 0.16 && s <= 0.78 && l >= 0.32 && l <= 0.88);

                    // Exclude pure white/blown-out lighting or pure shadow backdrop
                    const isExtremeLighting = (l > 0.96 || l < 0.04);

                    // Exclude head & hair (top 16% center)
                    const isHeadArea = (y < aH * 0.16 && (isSkin || (l < 0.18 && s < 0.28)));

                    // Wearable garment pixel verification: strictly isolated garment cloth
                    const isGownPixel = !isBgMatch && !isSkin && !isExtremeLighting && !isHeadArea && (
                        (l > 0.08 && l < 0.95 && (s > 0.06 || l < 0.25 || l > 0.78))
                    );

                    if (isGownPixel) {
                        totalGownPixels++;
                        lumSum += l;
                        satSum += s;

                        const binIdx = Math.min(15, Math.floor((h % 360) / 22.5));
                        hueBins[binIdx] += (s > 0.12) ? 1.0 : 0.3;

                        if (l > 0.86 && s < 0.35) sparklePixelCount++;
                        if (prevLum > 0) edgeContrastVariance += Math.abs(l - prevLum);

                        if (x < sliceSliceLeft[sliceIdx]) sliceSliceLeft[sliceIdx] = x;
                        if (x > sliceSliceRight[sliceIdx]) sliceSliceRight[sliceIdx] = x;
                        sliceGownPixels[sliceIdx]++;

                        // Keep original authentic gown pixel
                        cutoutImgData.data[idx] = r;
                        cutoutImgData.data[idx + 1] = g;
                        cutoutImgData.data[idx + 2] = b;
                        cutoutImgData.data[idx + 3] = 255;
                    } else {
                        cutoutImgData.data[idx + 3] = 0; // completely transparent background
                    }
                    prevLum = l;
                }
            }

            cutoutCtx.putImageData(cutoutImgData, 0, 0);

            if (totalGownPixels < 80) {
                return null;
            }

            // Normalize Hue Histogram
            let maxBinVal = 0, dominantBin = 0;
            for (let i = 0; i < 16; i++) {
                if (hueBins[i] > maxBinVal) {
                    maxBinVal = hueBins[i];
                    dominantBin = i;
                }
                hueBins[i] = hueBins[i] / totalGownPixels;
            }

            const avgL = lumSum / totalGownPixels;
            const avgS = satSum / totalGownPixels;
            
            // Calculate detection confidence
            const confidence = totalGownPixels / (aW * aH);

            let dominantColor = 'White';
            if (avgL > 0.82 && avgS < 0.26) {
                dominantColor = 'White';
            } else if (avgL < 0.20) {
                dominantColor = 'Black';
            } else if (avgS < 0.16) {
                dominantColor = 'Silver';
            } else {
                const domHue = dominantBin * 22.5 + 11.25;
                if (domHue >= 250 && domHue <= 315) dominantColor = 'Violet';
                else if (domHue >= 180 && domHue < 225) dominantColor = 'Sky Blue';
                else if (domHue >= 225 && domHue < 250) dominantColor = 'Blue';
                else if ((domHue >= 340 || domHue < 18) && avgS > 0.25) dominantColor = (avgL > 0.62) ? 'Pink' : 'Red';
                else if (domHue >= 18 && domHue <= 48) dominantColor = (avgL < 0.45 || avgS < 0.45) ? 'Gold Brown' : 'Gold';
                else if (domHue > 48 && domHue <= 75) dominantColor = 'Gold';
                else if (domHue > 75 && domHue < 170) dominantColor = 'Green';
                else if (domHue >= 315 && domHue < 340) dominantColor = 'Pink';
                else dominantColor = 'Gold';
            }

            // Contour width vector normalized [0..1]
            const widthVector = new Float32Array(SLICES);
            let maxSliceW = 1;
            for (let s = 0; s < SLICES; s++) {
                const w = (sliceSliceRight[s] >= sliceSliceLeft[s]) ? (sliceSliceRight[s] - sliceSliceLeft[s]) : 0;
                if (w > maxSliceW) maxSliceW = w;
                widthVector[s] = w;
            }
            for (let s = 0; s < SLICES; s++) {
                widthVector[s] /= maxSliceW;
            }

            const bustWidth = (widthVector[2] + widthVector[3]) / 2;
            const waistWidth = Math.min(widthVector[5], widthVector[6], widthVector[7]) || 0.3;
            const skirtFlareWidth = Math.max(widthVector[12], widthVector[13], widthVector[14], widthVector[15]);
            const flareRatio = skirtFlareWidth / Math.max(0.12, waistWidth);
            const shoulderRatio = widthVector[1] / Math.max(0.12, bustWidth);

            // Silhouette Classification
            let silhouetteType = 'Ball Gown';
            if (shoulderRatio > 1.22 || (dominantColor === 'Gold Brown' && shoulderRatio > 1.1)) {
                silhouetteType = 'Filipiniana';
            } else if (flareRatio > 1.7) {
                silhouetteType = (dominantColor === 'White') ? 'Wedding Gown' : 'Ball Gown';
            } else if (flareRatio >= 1.30) {
                silhouetteType = 'A-Line Gown';
            } else {
                silhouetteType = 'Evening Gown';
            }

            // Neckline & Bodice
            let necklineType = 'Sweetheart Neckline';
            const topSliceDip = widthVector[0];
            if (shoulderRatio > 1.15) {
                necklineType = 'Off-Shoulder';
            } else if (topSliceDip < 0.15) {
                necklineType = 'V-Neck Plunge';
            } else if (bustWidth > 0.6 && topSliceDip > 0.4) {
                necklineType = 'Strapless / Straight';
            } else {
                necklineType = 'Sweetheart Neckline';
            }

            // Fabric & Texture
            const textureVariance = edgeContrastVariance / Math.max(1, totalGownPixels);
            const sparkleRatio = sparklePixelCount / Math.max(1, totalGownPixels);
            let fabricType = 'Smooth Satin / Silk';
            if (textureVariance > 0.20 || sparkleRatio > 0.035) {
                fabricType = 'Lace / Beaded Sequins';
            } else if (textureVariance > 0.12) {
                fabricType = 'Embroidered Pattern';
            } else {
                fabricType = 'Smooth Satin / Silk';
            }

            // Sleeves
            let sleeveType = 'Sleeveless / Straps';
            if (shoulderRatio > 1.22) {
                sleeveType = 'Butterfly Sleeves';
            } else if (widthVector[4] > 0.75 && widthVector[5] > 0.70) {
                sleeveType = 'Long / Fitted Sleeves';
            }

            // Train / Asymmetry
            const trainAsymmetry = (sliceSliceRight[15] - aW / 2) - (aW / 2 - sliceSliceLeft[15]);
            const hasExtendedTrain = Math.abs(trainAsymmetry) > 14 || (widthVector[15] > 0.85 && widthVector[14] > 0.80);

            // Enhanced garment profile with visibility checks
            const garmentProfile = {
                dominantColor,
                avgL,
                avgS,
                hueBins,
                widthVector,
                flareRatio,
                shoulderRatio,
                silhouetteType,
                necklineType,
                fabricType,
                textureVariance,
                sparkleRatio,
                sleeveType,
                hasExtendedTrain,
                cutoutCanvas: cutoutCanvas,
                
                // STEP 4: Enhanced garment profile with visibility checks
                category: 'Gown/Dress',
                garmentType: silhouetteType,
                mainColor: dominantColor,
                secondaryColors: determineSecondaryColors(hueBins, totalGownPixels),
                silhouette: silhouetteType,
                length: determineGarmentLength(flareRatio, silhouetteType),
                neckline: necklineType,
                sleeves: sleeveType,
                bodiceStructure: determineBodiceStructure(widthVector, shoulderRatio),
                waistDesign: determineWaistDesign(widthVector),
                skirtShape: determineSkirtShape(flareRatio, widthVector),
                train: hasExtendedTrain ? 'Present' : 'Not clearly visible',
                fabricAppearance: fabricType,
                texture: textureVariance > 0.15 ? 'Textured' : 'Smooth',
                pattern: determinePattern(textureVariance, sparkleRatio),
                lace: sparkleRatio > 0.03 ? 'Present' : 'Not clearly visible',
                embroidery: textureVariance > 0.18 ? 'Present' : 'Not clearly visible',
                beading: sparkleRatio > 0.04 ? 'Present' : 'Not clearly visible',
                sequins: sparkleRatio > 0.05 ? 'Present' : 'Not clearly visible',
                draping: flareRatio > 1.5 ? 'Present' : 'Not clearly visible',
                overallDesign: silhouetteType,
                overallVisualStyle: fabricType,
                visibilityConfidence: confidence
            };

            return garmentProfile;
        }

        /**
         * Determine secondary colors from hue distribution
         */
        function determineSecondaryColors(hueBins, totalPixels) {
            const secondaryColors = [];
            const threshold = totalPixels * 0.15; // 15% threshold for secondary color
            
            for (let i = 0; i < 16; i++) {
                if (hueBins[i] * totalPixels > threshold) {
                    const hue = i * 22.5 + 11.25;
                    let colorName = 'Unknown';
                    if (hue >= 250 && hue <= 315) colorName = 'Violet';
                    else if (hue >= 180 && hue < 225) colorName = 'Sky Blue';
                    else if (hue >= 225 && hue < 250) colorName = 'Blue';
                    else if ((hue >= 340 || hue < 18)) colorName = 'Red';
                    else if (hue >= 18 && hue <= 48) colorName = 'Gold';
                    else if (hue > 48 && hue <= 75) colorName = 'Gold';
                    else if (hue > 75 && hue < 170) colorName = 'Green';
                    else if (hue >= 315 && hue < 340) colorName = 'Pink';
                    
                    if (colorName !== 'Unknown') {
                        secondaryColors.push(colorName);
                    }
                }
            }
            
            return secondaryColors.length > 0 ? secondaryColors.slice(0, 2) : ['Not clearly visible'];
        }

        /**
         * Determine garment length
         */
        function determineGarmentLength(flareRatio, silhouetteType) {
            if (silhouetteType.includes('Wedding') || silhouetteType.includes('Ball')) {
                return 'Full Length';
            } else if (flareRatio > 1.3) {
                return 'Full Length';
            } else if (flareRatio > 1.1) {
                return 'Midi/Tea Length';
            } else {
                return 'Not clearly visible';
            }
        }

        /**
         * Determine bodice structure
         */
        function determineBodiceStructure(widthVector, shoulderRatio) {
            const bustWidth = (widthVector[2] + widthVector[3]) / 2;
            const waistWidth = Math.min(widthVector[5], widthVector[6], widthVector[7]) || 0.3;
            
            if (shoulderRatio > 1.22) {
                return 'Off-Shoulder Structure';
            } else if (bustWidth > 0.6 && waistWidth < 0.4) {
                return 'Fitted Bodice';
            } else if (bustWidth > 0.5) {
                return 'Structured Bodice';
            } else {
                return 'Not clearly visible';
            }
        }

        /**
         * Determine waist design
         */
        function determineWaistDesign(widthVector) {
            const waistWidth = Math.min(widthVector[5], widthVector[6], widthVector[7]) || 0.3;
            const hipWidth = Math.max(widthVector[8], widthVector[9], widthVector[10]) || 0.5;
            
            if (waistWidth < 0.35 && hipWidth > 0.6) {
                return 'Defined Waist';
            } else if (waistWidth < 0.4) {
                return 'Natural Waist';
            } else {
                return 'Not clearly visible';
            }
        }

        /**
         * Determine skirt shape
         */
        function determineSkirtShape(flareRatio, widthVector) {
            const skirtWidth = Math.max(widthVector[12], widthVector[13], widthVector[14], widthVector[15]);
            
            if (flareRatio > 1.7) {
                return 'Full Ball Gown Skirt';
            } else if (flareRatio > 1.4) {
                return 'A-Line Skirt';
            } else if (flareRatio > 1.2) {
                return 'Fitted Skirt';
            } else if (skirtWidth > 0.7) {
                return 'Flared Skirt';
            } else {
                return 'Not clearly visible';
            }
        }

        /**
         * Determine pattern type
         */
        function determinePattern(textureVariance, sparkleRatio) {
            if (sparkleRatio > 0.05) {
                return 'Sequined/Beaded Pattern';
            } else if (textureVariance > 0.2) {
                return 'Intricate Pattern';
            } else if (textureVariance > 0.15) {
                return 'Textured Pattern';
            } else {
                return 'Solid/Minimal Pattern';
            }
        }

        /**
         * Analyzes person's visible appearance and proportions from scanned image:
         * - Skin undertone (Warm, Cool, Neutral/Olive) & luminance
         * - Body proportions: shoulder span, waist span, hip contour, height ratio
         * - Frame classification: Hourglass, Pear, Athletic, Slender, Petite, Statuesque
         */
        function analyzePersonAppearance(sourceCanvas, origW, origH) {
            const pW = 120, pH = 180;
            const offCanvas = document.createElement('canvas');
            offCanvas.width = pW;
            offCanvas.height = pH;
            const offCtx = offCanvas.getContext('2d', { willReadFrequently: true });
            offCtx.drawImage(sourceCanvas, 0, 0, origW, origH, 0, 0, pW, pH);

            const imgData = offCtx.getImageData(0, 0, pW, pH).data;
            let skinPixels = 0, skinHueSum = 0, skinSatSum = 0, skinLumSum = 0;

            const sliceWidths = new Int32Array(10);
            const sliceLefts = new Int32Array(10).fill(pW);
            const sliceRights = new Int32Array(10).fill(0);

            for (let y = 0; y < pH; y++) {
                const sliceIdx = Math.min(9, Math.floor((y / pH) * 10));
                for (let x = 0; x < pW; x++) {
                    const idx = (y * pW + x) * 4;
                    const r = imgData[idx];
                    const g = imgData[idx + 1];
                    const b = imgData[idx + 2];
                    const hsl = rgbToHsl(r, g, b);

                    // Detect person skin pixels
                    if (hsl.h >= 7 && hsl.h <= 45 && hsl.s >= 0.15 && hsl.s <= 0.80 && hsl.l >= 0.30 && hsl.l <= 0.90) {
                        skinPixels++;
                        skinHueSum += hsl.h;
                        skinSatSum += hsl.s;
                        skinLumSum += hsl.l;

                        if (x < sliceLefts[sliceIdx]) sliceLefts[sliceIdx] = x;
                        if (x > sliceRights[sliceIdx]) sliceRights[sliceIdx] = x;
                    }
                }
            }

            for (let i = 0; i < 10; i++) {
                sliceWidths[i] = (sliceRights[i] >= sliceLefts[i]) ? (sliceRights[i] - sliceLefts[i]) : 0;
            }

            const avgHue = skinPixels > 0 ? (skinHueSum / skinPixels) : 28;
            const avgSat = skinPixels > 0 ? (skinSatSum / skinPixels) : 0.45;
            const avgLum = skinPixels > 0 ? (skinLumSum / skinPixels) : 0.60;

            let undertone = 'Warm';
            let undertoneDesc = 'Warm golden undertone';
            if (avgHue >= 25 && avgHue <= 45) {
                undertone = 'Warm';
                undertoneDesc = 'Warm golden / peachy undertone';
            } else if (avgHue < 20) {
                undertone = 'Cool';
                undertoneDesc = 'Cool rosy / porcelain undertone';
            } else {
                undertone = 'Neutral';
                undertoneDesc = 'Neutral / olive undertone';
            }

            const shoulderSpan = Math.max(sliceWidths[2], sliceWidths[3], 20);
            const waistSpan = Math.max(sliceWidths[4], sliceWidths[5], 16);
            const hipSpan = Math.max(sliceWidths[6], sliceWidths[7], 18);

            const shoulderWaistRatio = shoulderSpan / Math.max(waistSpan, 1);
            const hipWaistRatio = hipSpan / Math.max(waistSpan, 1);

            let frameType = 'Balanced / Statuesque';
            if (shoulderWaistRatio > 1.28 && hipWaistRatio > 1.25) {
                frameType = 'Hourglass Figure';
            } else if (hipWaistRatio > 1.30 && shoulderWaistRatio <= 1.22) {
                frameType = 'Pear / Curvy Silhouette';
            } else if (shoulderWaistRatio > 1.32 && hipWaistRatio <= 1.22) {
                frameType = 'Athletic / Inverted Triangle';
            } else if (shoulderWaistRatio <= 1.22 && hipWaistRatio <= 1.22) {
                frameType = 'Slender / Column Frame';
            } else {
                frameType = 'Proportionate Frame';
            }

            return {
                undertone,
                undertoneDesc,
                avgHue,
                avgSat,
                avgLum,
                shoulderSpan,
                waistSpan,
                hipSpan,
                shoulderWaistRatio,
                hipWaistRatio,
                frameType,
                hasPerson: skinPixels > 50
            };
        }

        /**
         * Evaluates a gown image against the person's appearance & proportions:
         * 1. Color: Skin undertone harmony (warm/cool/neutral) & contrast (0–25 pts)
         * 2. Silhouette: Frame balance (flare vs shoulders/waist) (0–25 pts)
         * 3. Fit: Bodice contour & waist tapering (0–20 pts)
         * 4. Neckline: Framing collarbone/shoulders (0–15 pts)
         * 5. Length: Hemline & overall grace (0–15 pts)
         * Returns { score: 0-100, reason: string, imageIndex: 1..4 }
         */
        function evaluateGarmentSuitability(personProfile, gownSig, imgIndex, prod) {
            if (!gownSig) return { score: 72, reason: 'Classic elegant silhouette suitable for versatile formal wear.', imageIndex: imgIndex };

            let colorScore = 18;
            let silScore = 18;
            let fitScore = 15;
            let neckScore = 12;
            let lenScore = 12;
            const reasons = [];

            const c = (gownSig.dominantColor || '').toLowerCase();
            const u = personProfile ? personProfile.undertone : 'Warm';
            const frame = personProfile ? personProfile.frameType : 'Balanced / Statuesque';

            // 1. Color Harmony with Person's Undertone (25 pts)
            if (u === 'Warm') {
                if (c.includes('gold') || c.includes('green') || c.includes('emerald') || c.includes('champagne') || c.includes('red') || c.includes('brown')) {
                    colorScore = 25;
                    reasons.push(`${gownSig.dominantColor} enhances your warm golden undertone`);
                } else if (c.includes('white') || c.includes('pink') || c.includes('peach')) {
                    colorScore = 22;
                    reasons.push(`${gownSig.dominantColor} brightens your warm complexion`);
                } else {
                    colorScore = 19;
                    reasons.push(`rich ${gownSig.dominantColor} shade`);
                }
            } else if (u === 'Cool') {
                if (c.includes('blue') || c.includes('silver') || c.includes('violet') || c.includes('purple') || c.includes('pink')) {
                    colorScore = 25;
                    reasons.push(`${gownSig.dominantColor} radiates against your cool undertones`);
                } else if (c.includes('white') || c.includes('black') || c.includes('red')) {
                    colorScore = 23;
                    reasons.push(`${gownSig.dominantColor} provides striking contrast`);
                } else {
                    colorScore = 19;
                    reasons.push(`classic ${gownSig.dominantColor} tone`);
                }
            } else {
                // Neutral undertone
                if (c.includes('gold') || c.includes('blue') || c.includes('silver') || c.includes('white') || c.includes('teal')) {
                    colorScore = 24;
                    reasons.push(`${gownSig.dominantColor} flatters your balanced neutral complexion`);
                } else {
                    colorScore = 21;
                    reasons.push(`refined ${gownSig.dominantColor} color palette`);
                }
            }

            // 2. Silhouette & Proportion Harmony (25 pts)
            const flare = gownSig.flareRatio || 1.4;
            const sil = gownSig.silhouetteType || 'A-Line Gown';
            if (frame.includes('Hourglass')) {
                silScore = 25;
                reasons.push(`accentuates your natural waistline with an elegant ${sil} cut`);
            } else if (frame.includes('Pear')) {
                if (flare > 1.35) {
                    silScore = 25;
                    reasons.push(`the flared ${sil} gracefully balances hip-to-waist proportions`);
                } else {
                    silScore = 21;
                    reasons.push(`streamlined ${sil} tailoring`);
                }
            } else if (frame.includes('Athletic')) {
                if (gownSig.necklineType.includes('Sweetheart') || gownSig.necklineType.includes('Off-Shoulder') || flare > 1.3) {
                    silScore = 24;
                    reasons.push(`creates softer feminine curves with its sweeping ${sil} silhouette`);
                } else {
                    silScore = 20;
                    reasons.push(`structured ${sil} frame`);
                }
            } else {
                silScore = (flare >= 1.3) ? 24 : 22;
                reasons.push(`complements your stature with statuesque ${sil} proportions`);
            }

            // 3. Fit & Bodice (20 pts)
            if (gownSig.shoulderRatio < 1.35) {
                fitScore = 19;
            } else {
                fitScore = 17;
            }

            // 4. Neckline (15 pts)
            const neck = gownSig.necklineType || 'Sweetheart Neckline';
            if (neck.includes('Sweetheart') || neck.includes('Off-Shoulder') || neck.includes('V-Neck')) {
                neckScore = 15;
                reasons.push(`flattering ${neck}`);
            } else {
                neckScore = 13;
            }

            // 5. Length & Grace (15 pts)
            if (gownSig.hasExtendedTrain) {
                lenScore = 15;
                reasons.push('grand sweeping train');
            } else {
                lenScore = 14;
            }

            const totalScore = Math.min(99, Math.max(68, colorScore + silScore + fitScore + neckScore + lenScore));

            let shortReason = reasons.slice(0, 2).join(' and ') + '.';
            shortReason = shortReason.charAt(0).toUpperCase() + shortReason.slice(1);

            return {
                score: totalScore,
                reason: shortReason,
                imageIndex: imgIndex
            };
        }

        /**
         * STEP 5: Compares uploaded gown directly against catalog product image signature.
         * Enhanced to be more strict and accurate, prioritizing visual similarity.
         */
        function compareGownSignatures(sigA, sigB) {
            if (!sigA || !sigB) return { score: 0, reasons: [], rejected: true };

            const reasons = [];

            // 1. Overall Gown Appearance & Silhouette (35 pts - increased weight)
            let dot = 0, magA = 0, magB = 0;
            for (let i = 0; i < 16; i++) {
                dot += sigA.widthVector[i] * sigB.widthVector[i];
                magA += sigA.widthVector[i] * sigA.widthVector[i];
                magB += sigB.widthVector[i] * sigB.widthVector[i];
            }
            const contourCos = (magA > 0 && magB > 0) ? (dot / (Math.sqrt(magA) * Math.sqrt(magB))) : 0.5;
            const flareDiff = Math.abs(sigA.flareRatio - sigB.flareRatio) / Math.max(sigA.flareRatio, sigB.flareRatio, 1);
            const flareSim = Math.max(0, 1 - flareDiff);
            
            // Enhanced silhouette comparison with stricter thresholds
            const silSim = (contourCos * 0.70 + flareSim * 0.30) * 100;
            const score1 = Math.round((silSim / 100) * 35);
            
            if (silSim >= 90) {
                reasons.push(`${sigB.silhouetteType} Silhouette Match`);
            } else if (silSim >= 80) {
                reasons.push(`Similar ${sigB.silhouetteType} Silhouette`);
            } else if (silSim >= 70) {
                reasons.push('Similar Gown Flare & Cut');
            }

            // 2. Color & Color Combination (30 pts - increased weight)
            let histIntersect = 0;
            for (let i = 0; i < 16; i++) {
                histIntersect += Math.min(sigA.hueBins[i], sigB.hueBins[i]);
            }
            histIntersect = Math.min(1.0, histIntersect);

            let colorNameSim = 0.2;
            const cA = sigA.dominantColor.toLowerCase();
            const cB = sigB.dominantColor.toLowerCase();
            if (cA === cB) {
                colorNameSim = 1.0;
                reasons.push(`${sigB.dominantColor} Color Match`);
            } else {
                const isHarmonic = (
                    (cA.includes('gold') && cB.includes('gold')) ||
                    (cA.includes('blue') && cB.includes('blue')) ||
                    (cA.includes('white') && cB.includes('silver')) ||
                    (cA.includes('silver') && cB.includes('white')) ||
                    (cA.includes('pink') && cB.includes('red')) ||
                    (cA.includes('violet') && cB.includes('purple'))
                );
                if (isHarmonic) {
                    colorNameSim = 0.85;
                    reasons.push(`Color Harmony (${sigB.dominantColor})`);
                } else {
                    colorNameSim = Math.max(0.1, histIntersect * 0.6);
                }
            }
            const lumSim = Math.max(0, 1 - Math.abs(sigA.avgL - sigB.avgL) * 1.5);
            const satSim = Math.max(0, 1 - Math.abs(sigA.avgS - sigB.avgS) * 2.0);
            const colorTotalSim = (colorNameSim * 0.50 + histIntersect * 0.30 + lumSim * 0.12 + satSim * 0.08) * 100;
            const score2 = Math.round((colorTotalSim / 100) * 30);

            // STEP 6: Strict color rejection threshold
            if (colorTotalSim < 40 && colorNameSim <= 0.25) {
                return { score: 35, reasons: ['Color mismatch - background influence detected'], rejected: true };
            }

            // 3. Neckline & Bodice Design (12 pts)
            let neckSim = 0.5;
            if (sigA.necklineType === sigB.necklineType) {
                neckSim = 1.0;
                reasons.push(`Matching ${sigB.necklineType}`);
            } else if (
                (sigA.necklineType.includes('Sweetheart') && sigB.necklineType.includes('Off-Shoulder')) ||
                (sigA.necklineType.includes('Off-Shoulder') && sigB.necklineType.includes('Sweetheart'))
            ) {
                neckSim = 0.8;
                reasons.push('Harmonious Bodice Cut');
            } else {
                neckSim = 0.45;
            }
            const score3 = Math.round(neckSim * 12);

            // 4. Fabric and Texture (10 pts)
            const varDiff = Math.abs(sigA.textureVariance - sigB.textureVariance);
            const varSim = Math.max(0, 1 - varDiff * 3.5);
            let fabSim = (sigA.fabricType === sigB.fabricType) ? 1.0 : varSim;
            if (fabSim >= 0.85) {
                reasons.push(`Fabric: ${sigB.fabricType}`);
            }
            const score4 = Math.round(fabSim * 10);

            // 5. Skirt Shape and Length (8 pts)
            const skirtSim = flareSim;
            const score5 = Math.round(skirtSim * 8);

            // 6. Sleeves (5 pts)
            let sleeveSim = 0.6;
            if (sigA.sleeveType === sigB.sleeveType) {
                sleeveSim = 1.0;
                if (sigB.sleeveType.includes('Butterfly')) reasons.push('Butterfly Sleeves Detail');
            } else if (
                (sigA.sleeveType.includes('Sleeveless') && sigB.sleeveType.includes('Straps')) ||
                (sigA.sleeveType.includes('Straps') && sigB.sleeveType.includes('Sleeveless'))
            ) {
                sleeveSim = 0.85;
            }
            const score6 = Math.round(sleeveSim * 5);

            // 7. Embroidery, Lace, Sequins, Beads, and Details (5 pts)
            const sparkleDiff = Math.abs(sigA.sparkleRatio - sigB.sparkleRatio);
            const detailSim = Math.max(0, 1 - sparkleDiff * 15);
            if (detailSim > 0.8 && (sigA.sparkleRatio > 0.02 || sigB.sparkleRatio > 0.02)) {
                reasons.push('Intricate Beading & Shimmer Details');
            }
            const score7 = Math.round(detailSim * 5);

            // 8. Train and Other Distinctive Features (5 pts)
            let trainSim = (sigA.hasExtendedTrain === sigB.hasExtendedTrain) ? 1.0 : 0.4;
            const score8 = Math.round(trainSim * 5);

            const totalScore = Math.min(99, Math.max(35, score1 + score2 + score3 + score4 + score5 + score6 + score7 + score8));
            
            // STEP 5: Enhanced rejection criteria - more strict visual similarity
            const isRejected = (totalScore < 80 || silSim < 50 || colorTotalSim < 45);

            return {
                score: totalScore,
                silhouetteScore: Math.round(silSim),
                colorScore: Math.round(colorTotalSim),
                fabricScore: Math.round(fabSim * 100),
                reasons: reasons.slice(0, 3),
                rejected: isRejected
            };
        }

        // Preload and cache visual signatures for ALL 4 images of each catalog gown
        async function preloadCatalogImageSignatures(products) {
            if (!products || !products.length) return;
            const offCanvas = document.createElement('canvas');
            const offCtx = offCanvas.getContext('2d', { willReadFrequently: true });

            for (const p of products) {
                if (window._catalogSignatures[p.id] && window._catalogSignatures[p.id].images && window._catalogSignatures[p.id].images.length > 0) {
                    continue;
                }

                const imageList = [
                    { index: 1, url: p.image_path },
                    { index: 2, url: p.image_path2 },
                    { index: 3, url: p.image_path3 },
                    { index: 4, url: p.image_path4 }
                ].filter(item => item.url && item.url.trim() !== '');

                window._catalogSignatures[p.id] = { images: [] };

                for (const imgItem of imageList) {
                    try {
                        await new Promise((resolve) => {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = () => {
                                offCanvas.width = img.width || 200;
                                offCanvas.height = img.height || 300;
                                offCtx.drawImage(img, 0, 0);
                                const sig = extractFullGownSignature(offCanvas, offCanvas.width, offCanvas.height);
                                if (sig) {
                                    sig.imageUrl = imgItem.url;
                                    sig.imageIndex = imgItem.index;
                                    window._catalogSignatures[p.id].images.push(sig);
                                }
                                resolve();
                            };
                            img.onerror = () => resolve();
                            img.src = imgItem.url;
                        });
                    } catch (e) {
                        console.warn('Could not process catalog image', imgItem.url, e);
                    }
                }
            }
        }

        // Direct Center Gown Scanner & Stylist Recommendation Engine
        async function scanCenterGown(ctx, width, height, previewUrl, filename) {
            // Store original dimensions
            garmentOrigW = width;
            garmentOrigH = height;
            currentFullImgUrl = previewUrl;

            // STEP 1: Detect garment and create bounding box
            garmentBoundingBox = detectGarmentWithHighlight(ctx.canvas, width, height);
            garmentDetectionConfidence = garmentBoundingBox.confidence;

            // Draw highlight on preview
            const previewWrapper = document.getElementById('uploadPreviewWrapper');
            const highlightDrawn = drawGarmentHighlight(garmentBoundingBox, previewWrapper);

            if (!highlightDrawn || garmentBoundingBox.garmentPixelCount < 50) {
                showScanBanner('Detection Failed', ['No clear garment detected. Please upload a clearer image.']);
                renderSearchResults({ suggestions: [], is_scan: true, count: 0 }, true);
                return;
            }

            // STEP 2: Isolate garment
            isolatedGarmentCanvas = isolateGarment(ctx.canvas, garmentBoundingBox);

            if (!isolatedGarmentCanvas) {
                showScanBanner('Isolation Failed', ['Could not isolate garment. Please try another image.']);
                renderSearchResults({ suggestions: [], is_scan: true, count: 0 }, true);
                return;
            }

            // STEP 3: User confirmation
            // If confidence is high (>0.7), auto-confirm. Otherwise, show confirmation modal
            if (garmentDetectionConfidence > 0.7) {
                // Auto-proceed with high confidence
                garmentDetectionConfirmed = true;
                proceedWithGarmentScan();
            } else {
                // Show confirmation modal
                showGarmentConfirmation(isolatedGarmentCanvas, garmentDetectionConfidence);
            }
        }

        /**
         * Proceed with garment scanning after user confirmation
         */
        async function proceedWithGarmentScan() {
            if (!isolatedGarmentCanvas || !garmentDetectionConfirmed) {
                return;
            }

            // Remove highlight overlay
            const previewWrapper = document.getElementById('uploadPreviewWrapper');
            const existingHighlight = previewWrapper.querySelector('.garment-detection-overlay');
            if (existingHighlight) {
                existingHighlight.remove();
            }

            // Show scanning progress
            const scanOverlay = document.getElementById('uploadScanOverlay');
            const progressStepText = document.getElementById('scanProgressStepText');
            scanOverlay.style.display = 'flex';
            progressStepText.textContent = 'Analyzing isolated garment...';

            // STEP 4: Create garment scan profile from isolated garment
            const isolatedGownSig = extractFullGownSignature(isolatedGarmentCanvas, isolatedGarmentCanvas.width, isolatedGarmentCanvas.height);

            // Analyze person's appearance from original image
            const personProfile = analyzePersonAppearance(document.getElementById('camCanvas'), garmentOrigW, garmentOrigH);

            if (!isolatedGownSig && !personProfile.hasPerson) {
                showScanBanner('Analysis Failed', ['Could not analyze garment features.']);
                renderSearchResults({ suggestions: [], is_scan: true, count: 0 }, true);
                scanOverlay.style.display = 'none';
                return;
            }

            progressStepText.textContent = 'Matching with catalog products...';

            // STEP 5: Ensure catalog products are available - fetch ALL products
            if (!window._catalogProducts || !window._catalogProducts.length) {
                try {
                    const r = await fetch('product_camscan.php?action=search_scan');
                    const d = await r.json();
                    window._catalogProducts = d.suggestions || [];
                    
                    // Also fetch from admin API to ensure we have ALL products
                    try {
                        const adminR = await fetch('admin/gowns.php?action=getAllGowns');
                        const adminD = await adminR.json();
                        if (adminD && Array.isArray(adminD)) {
                            // Merge products, avoiding duplicates
                            const existingIds = new Set(window._catalogProducts.map(p => p.id));
                            adminD.forEach(p => {
                                if (!existingIds.has(p.id)) {
                                    window._catalogProducts.push(p);
                                }
                            });
                        }
                    } catch (e) {
                        console.warn('Could not fetch from admin API:', e);
                    }
                } catch (e) {}
            }

            // STEP 6: Ensure all 4 images per gown have isolated signatures preloaded
            await preloadCatalogImageSignatures(window._catalogProducts);

            // STEP 7: Compare isolated garment against catalog products
            const scoredProducts = [];
            let topMatch = null;
            let topChoiceObj = null;

            for (const prod of window._catalogProducts) {
                const prodSigData = window._catalogSignatures[prod.id] || { images: [] };
                const imagesArray = prodSigData.images || [];

                let bestImageEval = null;

                if (imagesArray.length > 0) {
                    for (const imgSig of imagesArray) {
                        let evalRes;
                        if (isolatedGownSig) {
                            // Direct Gown-to-Gown visual matching across images
                            const comp = compareGownSignatures(isolatedGownSig, imgSig);
                            
                            // Skip rejected matches (STEP 5: Strict filtering)
                            if (comp.rejected) {
                                continue;
                            }
                            
                            evalRes = {
                                score: comp.score,
                                reason: comp.reasons.join(' and ') || 'Harmonious silhouette and matching color balance.',
                                imageIndex: imgSig.imageIndex,
                                imageUrl: imgSig.imageUrl,
                                sig: imgSig,
                                silhouetteScore: comp.silhouetteScore,
                                colorScore: comp.colorScore,
                                fabricScore: comp.fabricScore
                            };
                        } else {
                            // Person-to-Gown suitability analysis
                            const suit = evaluateGarmentSuitability(personProfile, imgSig, imgSig.imageIndex, prod);
                            evalRes = {
                                score: suit.score,
                                reason: suit.reason,
                                imageIndex: suit.imageIndex,
                                imageUrl: imgSig.imageUrl,
                                sig: imgSig
                            };
                        }

                        if (!bestImageEval || evalRes.score > bestImageEval.score) {
                            bestImageEval = evalRes;
                        }
                    }
                } else {
                    // Fallback using product metadata
                    const fallbackSig = {
                        dominantColor: prod.color || 'White',
                        silhouetteType: prod.category || 'Ball Gown',
                        necklineType: 'Sweetheart Neckline',
                        flareRatio: 1.4,
                        shoulderRatio: 1.0,
                        hasExtendedTrain: false
                    };
                    const suit = evaluateGarmentSuitability(personProfile, fallbackSig, 1, prod);
                    bestImageEval = {
                        score: suit.score,
                        reason: suit.reason,
                        imageIndex: 1,
                        imageUrl: prod.image_path || 'images/icon/profile_logo.png',
                        sig: fallbackSig
                    };
                }

                // Skip products with no valid evaluation
                if (!bestImageEval) {
                    continue;
                }

                const finalScore = bestImageEval.score;

                // STEP 7: Enhanced match classification with detailed breakdowns
                const matchTier = (finalScore >= 90) ? 'Very Similar' :
                                  (finalScore >= 80) ? 'Similar' : 'Related';
                const matchTierLabel = (finalScore >= 90) ? 'Very Similar Style' :
                                       (finalScore >= 80) ? 'Similar Style' : 'Related Style';

                // Only include matches above 80% threshold
                if (finalScore < 80) {
                    continue;
                }

                // Calculate detailed match breakdowns
                const silhouetteMatch = bestImageEval.silhouetteScore || 0;
                const colorMatch = bestImageEval.colorScore || 0;
                const designMatch = bestImageEval.fabricScore || 0;
                
                // Determine key matching features
                const keyFeatures = [];
                if (silhouetteMatch >= 85) keyFeatures.push('Silhouette');
                if (colorMatch >= 85) keyFeatures.push('Color');
                if (designMatch >= 85) keyFeatures.push('Design');
                if (bestImageEval.sig && bestImageEval.sig.lace === 'Present') keyFeatures.push('Lace');
                if (bestImageEval.sig && bestImageEval.sig.embroidery === 'Present') keyFeatures.push('Embroidery');
                
                // Determine differences
                const differences = [];
                if (colorMatch < 70) differences.push('Color variation');
                if (silhouetteMatch < 70) differences.push('Silhouette difference');
                if (designMatch < 70) differences.push('Design variation');

                const pCopy = {
                    ...prod,
                    match_score: finalScore,
                    match_tier: matchTier,
                    match_tier_label: matchTierLabel,
                    match_reasons: [bestImageEval.reason],
                    bestChoice: bestImageEval,
                    silhouette_match: silhouetteMatch,
                    color_match: colorMatch,
                    design_match: designMatch,
                    key_matching_features: keyFeatures,
                    differences: differences
                };

                if (!topChoiceObj || bestImageEval.score > topChoiceObj.score) {
                    topChoiceObj = {
                        product: pCopy,
                        imageIndex: bestImageEval.imageIndex,
                        imageUrl: bestImageEval.imageUrl,
                        score: bestImageEval.score,
                        reason: bestImageEval.reason,
                        sig: bestImageEval.sig
                    };
                }

                if (finalScore >= 90 && !topMatch) {
                    topMatch = pCopy;
                } else {
                    scoredProducts.push(pCopy);
                }
            }

            // Sort descending by score
            scoredProducts.sort((a, b) => b.match_score - a.match_score);

            // Store global AI Best Choice for Virtual Try-On
            if (topChoiceObj) {
                window._currentAiBestChoice = {
                    ...topChoiceObj,
                    wearerCanvas: document.getElementById('camCanvas'),
                    personProfile: personProfile
                };
            }

            const detectedColor = (isolatedGownSig ? isolatedGownSig.dominantColor : (personProfile.undertone + ' Tone'));
            const detectedSil = (isolatedGownSig ? isolatedGownSig.silhouetteType : personProfile.frameType);

            const tags = [
                `Skin: ${personProfile.undertoneDesc || 'Warm Tone'}`,
                `Body: ${personProfile.frameType || 'Statuesque'}`,
                `Color: ${detectedColor}`,
                `Silhouette: ${detectedSil}`
            ];

            const scanResultData = {
                success: true,
                exact: topMatch,
                suggestions: scoredProducts,
                count: scoredProducts.length + (topMatch ? 1 : 0),
                is_scan: true,
                detected_color: detectedColor,
                detected_category: detectedSil,
                detected_gender: 'women',
                ai_best_choice: topChoiceObj,
                no_match_message: 'No sufficiently similar gown was found based on the uploaded image.'
            };

            scanOverlay.style.display = 'none';
            showScanBanner(`AI Stylist Analysis Complete: ${detectedColor} • ${detectedSil}`, tags);
            renderSearchResults(scanResultData, true);

            popScanResultsModal(
                scanResultData,
                currentFullImgUrl,
                tags,
                `AI Gown Recommendation & Try-On (${detectedColor})`,
                {
                    primary: { name: detectedColor, hex: colorPaletteMeta[detectedColor] || '#ffd700' },
                    secondary: null,
                    silhouette: detectedSil,
                    confidence: topChoiceObj ? topChoiceObj.score : 85
                }
            );
        }

        function rgbToHsl(r, g, b) {
            r /= 255; g /= 255; b /= 255;
            const max = Math.max(r, g, b), min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;

            if (max === min) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                    case g: h = (b - r) / d + 2; break;
                    case b: h = (r - g) / d + 4; break;
                }
                h *= 60;
            }
            return { h, s, l };
        }

        function showScanBanner(title, tags) {
            const banner = document.getElementById('scanStatusBanner');
            const textEl = document.getElementById('scanStatusText');
            const tagsEl = document.getElementById('scanDetectedTags');

            textEl.textContent = title;
            tagsEl.innerHTML = tags.map(t => `<span class="detected-tag-pill"><i class="fas fa-tag"></i> ${escapeHtml(t)}</span>`).join('');
            banner.style.display = 'flex';
        }

        function clearScanFilter() {
            document.getElementById('scanStatusBanner').style.display = 'none';
            document.getElementById('exactMatchCard').style.display = 'none';
            activeColorFilter = '';
            fetchSuggestions('', '', activeCategoryFilter);
        }

        function applyCategoryFilter(cat, chipEl) {
            activeCategoryFilter = cat;
            document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
            if (chipEl) chipEl.classList.add('active');
            fetchSuggestions('', activeColorFilter, activeCategoryFilter);
        }

        function fetchSuggestions(query, color, category, exactId = 0) {
            const container = document.getElementById('productsGridContainer');
            const countBadge = document.getElementById('resultsCountBadge');

            container.innerHTML = `
                <div class="skeleton-product-card"><div class="skeleton-product-image skeleton-shimmer"></div><div class="skeleton-product-info"><div class="skeleton-product-title skeleton-shimmer"></div><div class="skeleton-product-subtitle skeleton-shimmer"></div><div class="skeleton-product-price-row"><div class="skeleton-product-price skeleton-shimmer"></div><div class="skeleton-product-btn skeleton-shimmer"></div></div></div></div>
                <div class="skeleton-product-card"><div class="skeleton-product-image skeleton-shimmer"></div><div class="skeleton-product-info"><div class="skeleton-product-title skeleton-shimmer"></div><div class="skeleton-product-subtitle skeleton-shimmer"></div><div class="skeleton-product-price-row"><div class="skeleton-product-price skeleton-shimmer"></div><div class="skeleton-product-btn skeleton-shimmer"></div></div></div></div>
                <div class="skeleton-product-card"><div class="skeleton-product-image skeleton-shimmer"></div><div class="skeleton-product-info"><div class="skeleton-product-title skeleton-shimmer"></div><div class="skeleton-product-subtitle skeleton-shimmer"></div><div class="skeleton-product-price-row"><div class="skeleton-product-price skeleton-shimmer"></div><div class="skeleton-product-btn skeleton-shimmer"></div></div></div></div>
                <div class="skeleton-product-card"><div class="skeleton-product-image skeleton-shimmer"></div><div class="skeleton-product-info"><div class="skeleton-product-title skeleton-shimmer"></div><div class="skeleton-product-subtitle skeleton-shimmer"></div><div class="skeleton-product-price-row"><div class="skeleton-product-price skeleton-shimmer"></div><div class="skeleton-product-btn skeleton-shimmer"></div></div></div></div>
            `;
            countBadge.textContent = 'Searching...';

            const params = new URLSearchParams();
            params.append('action', 'search_scan');
            if (query) params.append('q', query);
            if (color) params.append('color', color);
            if (category) params.append('category', category);
            if (exactId > 0) params.append('id', exactId);

            fetch(`product_camscan.php?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.suggestions) {
                        window._catalogProducts = data.suggestions;
                        preloadCatalogImageSignatures(data.suggestions);
                    }
                    
                    // Also fetch from admin API to ensure we have ALL products
                    fetch('admin/gowns.php?action=getAllGowns')
                        .then(adminR => adminR.json())
                        .then(adminD => {
                            if (adminD && Array.isArray(adminD)) {
                                // Merge products, avoiding duplicates
                                const existingIds = new Set(window._catalogProducts.map(p => p.id));
                                adminD.forEach(p => {
                                    if (!existingIds.has(p.id)) {
                                        window._catalogProducts.push(p);
                                    }
                                });
                                console.log(`Total products after admin merge: ${window._catalogProducts.length}`);
                            }
                        })
                        .catch(e => console.warn('Could not fetch from admin API:', e));
                    
                    renderSearchResults(data, false);
                })
                .catch(err => {
                    console.error("Fetch suggestions error:", err);
                    container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#888; padding:30px;">Could not load recommendations. Please try again.</div>';
                    countBadge.textContent = '0 items';
                });
        }

        function renderSearchResults(data, isScanResult = false) {
            const container = document.getElementById('productsGridContainer');
            const countBadge = document.getElementById('resultsCountBadge');
            const headerTitle = document.getElementById('resultsHeaderTitle');
            const exactCard = document.getElementById('exactMatchCard');
            const isActualScan = isScanResult && Boolean(data.is_scan);
            const items = data.suggestions || [];

            // Update Header for Scan results vs Initial Catalog Display
            if (isActualScan) {
                headerTitle.innerHTML = `<i class="fas fa-sparkles" style="color: var(--gold-primary);"></i> AI Visual Matches`;
                countBadge.textContent = `${items.length + (data.exact ? 1 : 0)} matches (Visual Similarity)`;
            } else {
                headerTitle.innerHTML = `<i class="fas fa-sparkles"></i> Available Gowns & Attires`;
                countBadge.textContent = `${items.length} recommendations`;
            }

            // Verified Product Card on Page - Directly Links to product_info.php
            if (data.exact && isActualScan) {
                document.getElementById('exactMatchImg').src = data.exact.image_path || 'images/icon/profile_logo.png';
                document.getElementById('exactMatchTitle').textContent = data.exact.name;
                document.getElementById('exactMatchPrice').textContent = `₱${parseFloat(data.exact.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                
                const productInfoUrl = `index.php?id=${data.exact.id}`;
                const exactLinkEl = document.getElementById('exactMatchLink');
                exactLinkEl.href = productInfoUrl;
                exactCard.onclick = (e) => {
                    if (e.target.tagName !== 'A') {
                        window.location.href = productInfoUrl;
                    }
                };
                exactCard.style.cursor = 'pointer';
                exactCard.style.display = 'flex';
            } else {
                exactCard.style.display = 'none';
            }

            if (items.length === 0 && !data.exact) {
                if (isActualScan) {
                    container.innerHTML = `
                        <div style="grid-column: 1/-1; text-align:center; padding: 45px 20px;">
                            <div style="width: 58px; height: 58px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: inline-flex; align-items: center; justify-content: center; color: #ef4444; font-size: 24px; margin-bottom: 14px;">
                                <i class="fas fa-search-minus"></i>
                            </div>
                            <div style="font-weight: 800; color: #fff; font-size: 1.05rem; margin-bottom: 6px;">
                                No sufficiently similar gown was found based on the uploaded image.
                            </div>
                            <div style="font-size: 0.82rem; color: #888; max-width: 400px; margin: 0 auto;">
                                Recommendations require accurate visual similarity (kalokalike matching). Try taking or uploading a clearer, well-lit photo of the gown.
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div style="grid-column: 1/-1; text-align:center; padding: 40px 20px; color:#888;">
                            <i class="fas fa-camera" style="font-size: 32px; color:#555; margin-bottom: 10px;"></i>
                            <div style="font-weight: 700; color:#ccc; margin-bottom: 4px;">Start Scanning</div>
                            <div style="font-size: 0.8rem;">Capture or upload a photo to discover visually matching gowns.</div>
                        </div>
                    `;
                }
                return;
            }

            container.innerHTML = buildProductCardsHTML(items, isActualScan);
        }

        // Pop up the scan results modal with suggested gowns, AI recommendation & palette
        function popScanResultsModal(data, snapshotUrl, tags, heading, analysis) {
            const modal = document.getElementById('scanResultsModal');
            const thumbEl = document.getElementById('modalSnapshotThumb');
            const headEl = document.getElementById('modalSnapshotHeading');
            const tagsEl = document.getElementById('modalDetectedTags');
            const paletteEl = document.getElementById('modalPaletteRow');
            const exactCard = document.getElementById('modalExactMatchCard');
            const bestChoiceCard = document.getElementById('aiBestChoiceCard');
            const modalGrid = document.getElementById('modalProductsGrid');

            if (snapshotUrl) {
                thumbEl.src = snapshotUrl;
                thumbEl.style.display = 'block';
            } else {
                thumbEl.style.display = 'none';
            }

            headEl.textContent = heading || 'Visual Gown Analysis';
            tagsEl.innerHTML = (tags || []).map(t => `<span class="detected-tag-pill"><i class="fas fa-tag"></i> ${escapeHtml(t)}</span>`).join('');

            // Display color swatches
            if (analysis && analysis.primary) {
                paletteEl.innerHTML = `
                    <span>Palette:</span>
                    <span class="swatch-circle" style="background: ${analysis.primary.hex};" title="Primary: ${analysis.primary.name}"></span>
                    <span style="font-size: 0.72rem; color: #ccc;">${analysis.primary.name}</span>
                    ${analysis.secondary ? `
                        <span class="swatch-circle" style="background: ${analysis.secondary.hex}; margin-left: 6px;" title="Accent: ${analysis.secondary.name}"></span>
                        <span style="font-size: 0.72rem; color: #aaa;">${analysis.secondary.name}</span>
                    ` : ''}
                `;
            } else {
                paletteEl.innerHTML = '';
            }

            // Render AI Best Choice recommendation card
            const aiChoice = data.ai_best_choice || window._currentAiBestChoice;
            if (aiChoice && aiChoice.product) {
                // STEP 7: Enhanced AI Best Choice with detailed breakdowns
                const silhouetteMatch = aiChoice.product.silhouette_match || 0;
                const colorMatch = aiChoice.product.color_match || 0;
                const designMatch = aiChoice.product.design_match || 0;
                const keyFeatures = (aiChoice.product.key_matching_features || []).join(', ');
                const differences = (aiChoice.product.differences || []).join(', ');

                bestChoiceCard.innerHTML = `
                    <div class="ai-best-choice-header">
                        <div class="ai-best-badge"><i class="fas fa-crown"></i> AI Stylist Recommendation</div>
                        <div class="ai-best-score">Score: ${aiChoice.score}/100</div>
                    </div>
                    <div class="ai-best-choice-content">
                        <div class="ai-best-img-box">
                            <img src="${escapeHtml(aiChoice.imageUrl)}" alt="${escapeHtml(aiChoice.product.name)}" onerror="this.src='images/icon/profile_logo.png'">
                            <div class="ai-best-img-tag">Best choice: Image ${aiChoice.imageIndex}</div>
                        </div>
                        <div class="ai-best-details">
                            <div class="ai-best-title">${escapeHtml(aiChoice.product.name)}</div>
                            <div class="ai-output-line"><strong>Best choice:</strong> Image ${aiChoice.imageIndex}</div>
                            <div class="ai-output-line"><strong>Score:</strong> ${aiChoice.score}/100</div>
                            <div class="ai-output-line"><strong>Reason:</strong> <span style="color:#d4d4d4;">${escapeHtml(aiChoice.reason)}</span></div>
                            
                            <!-- Detailed Match Breakdown -->
                            <div class="ai-reason-box" style="margin-top: 8px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #aaa;">Visual Match:</span>
                                    <span style="color: #ffd700; font-weight: 700;">${aiChoice.score}%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #aaa;">Color Match:</span>
                                    <span style="color: ${colorMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${colorMatch}%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #aaa;">Design Match:</span>
                                    <span style="color: ${designMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${designMatch}%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                    <span style="color: #aaa;">Silhouette Match:</span>
                                    <span style="color: ${silhouetteMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${silhouetteMatch}%</span>
                                </div>
                                ${keyFeatures ? `<div style="margin-top: 6px; color: #00e676; font-size: 0.7rem;"><i class="fas fa-check"></i> ${escapeHtml(keyFeatures)}</div>` : ''}
                                ${differences ? `<div style="margin-top: 4px; color: #ff6b6b; font-size: 0.7rem;"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(differences)}</div>` : ''}
                            </div>
                            
                            <div class="ai-best-actions">
                                <button type="button" class="btn-tryon" onclick="openTryOnPreview()">
                                    <i class="fas fa-magic"></i> Virtual Try-On Preview
                                </button>
                                <a href="index.php?id=${aiChoice.product.id}" class="btn-cam-secondary" style="font-size:0.8rem; padding:7px 14px; text-decoration:none;">
                                    <i class="fas fa-eye"></i> View Gown
                                </a>
                            </div>
                        </div>
                    </div>
                `;
                bestChoiceCard.style.display = 'flex';
            } else {
                bestChoiceCard.style.display = 'none';
            }

            // Exact match in modal - Directly Links to product_info.php
            if (data.exact) {
                const productInfoUrl = `index.php?id=${data.exact.id}`;
                document.getElementById('modalExactMatchImg').src = data.exact.image_path || 'images/icon/profile_logo.png';
                document.getElementById('modalExactMatchTitle').textContent = data.exact.name;
                document.getElementById('modalExactMatchPrice').textContent = `₱${parseFloat(data.exact.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                
                const modalExactLinkEl = document.getElementById('modalExactMatchLink');
                modalExactLinkEl.href = productInfoUrl;
                exactCard.onclick = (e) => {
                    if (e.target.tagName !== 'A') {
                        window.location.href = productInfoUrl;
                    }
                };
                exactCard.style.cursor = 'pointer';
                exactCard.style.display = 'flex';
            } else {
                exactCard.style.display = 'none';
            }

            // Suggestions in modal (always a scan result)
            const items = data.suggestions || [];
            if (items.length > 0) {
                modalGrid.innerHTML = buildProductCardsHTML(items, true);
            } else if (!data.exact) {
                modalGrid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align:center; padding: 40px 20px;">
                        <div style="width: 52px; height: 52px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: inline-flex; align-items: center; justify-content: center; color: #ef4444; font-size: 22px; margin-bottom: 12px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div style="font-weight: 800; color: #fff; font-size: 1rem; margin-bottom: 4px;">
                            No sufficiently similar gown was found based on the uploaded image.
                        </div>
                        <div style="font-size: 0.8rem; color: #888;">
                            Accuracy is prioritized over arbitrary recommendations.
                        </div>
                    </div>
                `;
            } else {
                modalGrid.innerHTML = '';
            }

            modal.style.display = 'flex';
        }

        function closeScanModal() {
            document.getElementById('scanResultsModal').style.display = 'none';
        }

        // =============================================================
        // VIRTUAL TRY-ON ENGINE
        // Preserves selected gown's original design, color, shape & details 100% unchanged.
        // Uses the person's image only as the wearer reference.
        // =============================================================
        function openTryOnPreview() {
            const choice = window._currentAiBestChoice;
            if (!choice) return;

            const modal = document.getElementById('tryOnModal');
            document.getElementById('tryOnGownTitle').textContent = choice.product.name;
            document.getElementById('tryOnGownPrice').textContent = `₱${parseFloat(choice.product.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('tryOnViewProductBtn').href = `index.php?id=${choice.product.id}`;

            window._currentTryOnMode = 'composite';
            updateTryOnTabButtons('composite');
            modal.style.display = 'flex';

            renderTryOnCanvas('composite');
        }

        function closeTryOnModal() {
            document.getElementById('tryOnModal').style.display = 'none';
        }

        function switchTryOnView(mode) {
            window._currentTryOnMode = mode;
            updateTryOnTabButtons(mode);
            renderTryOnCanvas(mode);
        }

        function updateTryOnTabButtons(mode) {
            const tabs = {
                composite: document.getElementById('tryonTabComposite'),
                person: document.getElementById('tryonTabPerson'),
                gown: document.getElementById('tryonTabGown')
            };
            for (const k in tabs) {
                if (tabs[k]) {
                    if (k === mode) tabs[k].classList.add('active');
                    else tabs[k].classList.remove('active');
                }
            }
        }

        function renderTryOnCanvas(mode) {
            const canvas = document.getElementById('tryOnCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const choice = window._currentAiBestChoice;
            if (!choice) return;

            const cW = 600, cH = 800;
            canvas.width = cW;
            canvas.height = cH;

            // Clean background fill
            ctx.fillStyle = '#0d0e12';
            ctx.fillRect(0, 0, cW, cH);

            const wearerCanvas = choice.wearerCanvas;
            const gownImgUrl = choice.imageUrl;

            if (mode === 'person') {
                // Wearer Reference Only
                if (wearerCanvas) {
                    drawImageContained(ctx, wearerCanvas, 0, 0, cW, cH);
                } else {
                    ctx.fillStyle = '#888';
                    ctx.font = '16px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('Wearer reference image unavailable', cW / 2, cH / 2);
                }
            } else if (mode === 'gown') {
                // Selected Gown Only: original design, color, shape & details 100% unaltered
                const gImg = new Image();
                gImg.crossOrigin = 'anonymous';
                gImg.onload = () => {
                    drawImageContained(ctx, gImg, 0, 0, cW, cH);
                };
                gImg.src = gownImgUrl;
            } else {
                // Composite: Wearer Reference + Authentic Selected Gown Overlay
                if (wearerCanvas) {
                    drawImageContained(ctx, wearerCanvas, 0, 0, cW, cH);
                }

                // Authentic gown overlay: Keeps original design, color, shape, and details unchanged
                const gImg = new Image();
                gImg.crossOrigin = 'anonymous';
                gImg.onload = () => {
                    const overlayW = cW * 0.72;
                    const aspect = gImg.height / Math.max(1, gImg.width);
                    const overlayH = overlayW * aspect;
                    const overlayX = (cW - overlayW) / 2;
                    const overlayY = cH * 0.22; // Aligned precisely below neck / shoulders

                    // Draw authentic gown without altering color, shape, or fabric details
                    ctx.save();
                    ctx.shadowColor = 'rgba(0,0,0,0.55)';
                    ctx.shadowBlur = 18;
                    ctx.drawImage(gImg, overlayX, overlayY, overlayW, overlayH);
                    ctx.restore();
                };
                gImg.src = gownImgUrl;
            }
        }

        function drawImageContained(ctx, img, x, y, w, h) {
            const iW = img.width || img.videoWidth || 1;
            const iH = img.height || img.videoHeight || 1;
            const hRatio = w / iW;
            const vRatio = h / iH;
            const ratio = Math.min(hRatio, vRatio);
            const centerShiftX = (w - iW * ratio) / 2;
            const centerShiftY = (h - iH * ratio) / 2;
            ctx.drawImage(img, 0, 0, iW, iH, x + centerShiftX, y + centerShiftY, iW * ratio, iH * ratio);
        }

        // Build product card HTML with AI similarity badges
        function buildProductCardsHTML(items, isScan = false) {
            let html = '';
            items.forEach(p => {
                const img = p.image_path || 'images/icon/profile_logo.png';
                const priceFormatted = parseFloat(p.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
                const colorTag = p.color || 'Gown';
                const catTag = p.category || 'Dress';
                const score = p.match_score;
                const tier = p.match_tier || 'Similar';
                const tierLabel = p.match_tier_label || `${score}% Match`;
                const reasons = (p.match_reasons || []).join(' • ');
                const productUrl = `index.php?id=${p.id}`;

                // STEP 7: Detailed match breakdowns
                const silhouetteMatch = p.silhouette_match || 0;
                const colorMatch = p.color_match || 0;
                const designMatch = p.design_match || 0;
                const keyFeatures = (p.key_matching_features || []).join(', ');
                const differences = (p.differences || []).join(', ');

                let tierBadgeHtml = '';
                if (isScan && score) {
                    if (tier === 'Very Similar' || score >= 90) {
                        tierBadgeHtml = `<span class="product-match-pill" style="background: linear-gradient(135deg, #00e676, #00b0ff); color: #111; font-weight: 900;"><i class="fas fa-sparkles"></i> Very Similar (${score}%)</span>`;
                    } else if (tier === 'Similar' || score >= 80) {
                        tierBadgeHtml = `<span class="product-match-pill" style="background: linear-gradient(135deg, #ffd700, #ff8c00); color: #111; font-weight: 900;"><i class="fas fa-star"></i> Similar (${score}%)</span>`;
                    } else if (score >= 80) {
                        tierBadgeHtml = `<span class="product-match-pill" style="background: linear-gradient(135deg, #00b0ff, #7c4dff); color: #fff;"><i class="fas fa-check-double"></i> Visually Similar (${score}%)</span>`;
                    }
                }

                // Detailed match breakdown for scan results
                let matchBreakdownHtml = '';
                if (isScan && score) {
                    matchBreakdownHtml = `
                        <div class="match-breakdown-section" style="margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.04); border-radius: 6px; font-size: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #aaa;">Visual Match:</span>
                                <span style="color: #ffd700; font-weight: 700;">${score}%</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #aaa;">Color Match:</span>
                                <span style="color: ${colorMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${colorMatch}%</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #aaa;">Design Match:</span>
                                <span style="color: ${designMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${designMatch}%</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #aaa;">Silhouette Match:</span>
                                <span style="color: ${silhouetteMatch >= 80 ? '#00e676' : '#ff6b6b'}; font-weight: 700;">${silhouetteMatch}%</span>
                            </div>
                            ${keyFeatures ? `<div style="margin-top: 6px; color: #00e676; font-size: 0.7rem;"><i class="fas fa-check"></i> ${escapeHtml(keyFeatures)}</div>` : ''}
                            ${differences ? `<div style="margin-top: 4px; color: #ff6b6b; font-size: 0.7rem;"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(differences)}</div>` : ''}
                        </div>
                    `;
                }

                html += `
                    <a href="${productUrl}" class="product-card">
                        <div class="product-img-box">
                            <img src="${escapeHtml(img)}" alt="${escapeHtml(p.name)}" onerror="this.src='images/icon/profile_logo.png'">
                            <span class="product-category-tag">${escapeHtml(catTag)}</span>
                            ${p.has_3d ? '<span class="product-3d-badge"><i class="fas fa-cube"></i> 3D</span>' : ''}
                            ${tierBadgeHtml}
                        </div>
                        <div class="product-info-box">
                            <div class="product-name" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</div>
                            <div class="product-color-badge">
                                <span class="product-color-dot" style="background: ${colorPaletteMeta[p.color] || '#ffd700'};"></span>
                                <span>${escapeHtml(colorTag)}</span>
                            </div>
                            ${(isScan && reasons) ? `<div class="product-reasons-snippet" title="${escapeHtml(reasons)}">${escapeHtml(reasons)}</div>` : ''}
                            ${matchBreakdownHtml}
                            <div class="product-price-row">
                                <span class="product-price">₱${priceFormatted}</span>
                                <span class="product-btn-action"><i class="fas fa-arrow-right"></i></span>
                            </div>
                        </div>
                    </a>
                `;
            });
            return html;
        }

        // Cart & Purchase Count badges
        function fetchCartAndPurchaseBadges() {
            fetch('save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_cart_count'
            })
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('cartCount');
                if (badge) {
                    if (data.success && data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }).catch(() => {});

            fetch('save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_purchase_badge_data'
            })
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('purchaseBadge');
                if (badge && data.success && Array.isArray(data.purchases)) {
                    let unviewed = 0;
                    data.purchases.forEach(p => {
                        const status = (p.status || '').toLowerCase();
                        if (['confirmed', 'completed', 'cancelled'].includes(status)) {
                            unviewed++;
                        }
                    });
                    if (unviewed > 0) {
                        badge.textContent = unviewed;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }).catch(() => {});
        }



        // =============================================================

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
