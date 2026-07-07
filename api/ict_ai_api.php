<?php
// api/ict_bot_api.php - ICT Assistance Bot API with Full System Knowledge
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$db = getDB();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'Staff';
$user_department = $_SESSION['department'] ?? '';
$user_name = $_SESSION['first_name'] ?? 'User';

// ===== CONFIGURATION =====
$apiKey = getenv('GROQ_API_KEY') ?: $_ENV['GROQ_API_KEY'];
$apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
$model = 'llama-3.1-8b-instant';

// ============================================================
// SECTION I: GET FULL SYSTEM KNOWLEDGE
// ============================================================
function getFullSystemKnowledge($db, $user_role, $user_department) {
    $knowledge = [];
    
    // I. INSTITUTION INFO
    $knowledge['institution'] = [
        'name' => '🏛️ Institute of Finance Management (IFM)',
        'motto' => 'Excellence in Finance and Management',
        'department' => '💻 ICT Department',
        'system' => '📋 ICT Asset Management and Fault Detection System',
        'purpose' => 'To efficiently manage ICT assets, track maintenance schedules, and streamline fault reporting',
        'vision' => 'To be a center of excellence in ICT asset management and technical support',
        'mission' => 'To provide reliable, efficient, and innovative ICT solutions for IFM'
    ];
    
    // II. ASSET CATEGORIES WITH COUNTS
    $stmt = $db->query("SELECT c.category_name, c.description, COUNT(a.asset_id) as total 
                        FROM asset_categories c 
                        LEFT JOIN assets a ON c.category_name = a.category 
                        GROUP BY c.category_name 
                        ORDER BY total DESC");
    $knowledge['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // III. TOTAL ASSETS
    $stmt = $db->query("SELECT COUNT(*) as total FROM assets");
    $knowledge['total_assets'] = $stmt->fetch()['total'];
    
    // IV. ASSETS BY STATUS WITH PERCENTAGES
    $stmt = $db->query("SELECT 
                        status, 
                        COUNT(*) as count,
                        ROUND((COUNT(*) / (SELECT COUNT(*) FROM assets)) * 100, 1) as percentage
                        FROM assets 
                        GROUP BY status 
                        ORDER BY count DESC");
    $knowledge['assets_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // V. ASSETS BY DEPARTMENT WITH DETAILS
    $stmt = $db->query("SELECT 
                        location, 
                        COUNT(*) as count,
                        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
                        SUM(CASE WHEN status = 'In Use' THEN 1 ELSE 0 END) as in_use,
                        SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as under_maintenance
                        FROM assets 
                        GROUP BY location 
                        ORDER BY count DESC");
    $knowledge['assets_by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // VI. RECENT ASSETS (Last 10)
    $stmt = $db->query("SELECT name, category, status, location, asset_tag, created_at 
                        FROM assets 
                        ORDER BY created_at DESC 
                        LIMIT 10");
    $knowledge['recent_assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // VII. ASSET DISTRIBUTION SUMMARY
    $stmt = $db->query("SELECT 
                        COUNT(DISTINCT category) as total_categories,
                        COUNT(DISTINCT location) as total_locations,
                        MAX(created_at) as last_added
                        FROM assets");
    $knowledge['asset_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // VIII. USERS STATS WITH DETAILS
    $stmt = $db->query("SELECT 
                        role, 
                        COUNT(*) as count,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                        FROM users 
                        GROUP BY role 
                        ORDER BY count DESC");
    $knowledge['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                        FROM users");
    $knowledge['users_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // IX. MAINTENANCE STATS WITH RATES
    $stmt = $db->query("SELECT 
                        COUNT(*) as total_requests,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
                        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
                        SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed,
                        ROUND((SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as resolution_rate
                        FROM maintenance_requests");
    $knowledge['maintenance_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // X. REQUESTS BY PRIORITY
    $stmt = $db->query("SELECT 
                        priority, 
                        COUNT(*) as count,
                        ROUND((COUNT(*) / (SELECT COUNT(*) FROM maintenance_requests)) * 100, 1) as percentage
                        FROM maintenance_requests 
                        GROUP BY priority 
                        ORDER BY FIELD(priority, 'Critical', 'High', 'Medium', 'Low')");
    $knowledge['requests_by_priority'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // XI. RECENT REQUESTS (Last 10)
    $stmt = $db->query("SELECT r.request_id, a.name as asset_name, r.status, r.priority, 
                        CONCAT(u.first_name, ' ', u.last_name) as reported_by,
                        r.reported_at 
                        FROM maintenance_requests r 
                        LEFT JOIN assets a ON r.asset_id = a.asset_id 
                        LEFT JOIN users u ON r.reported_by = u.user_id 
                        ORDER BY r.reported_at DESC 
                        LIMIT 10");
    $knowledge['recent_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // XII. TOP FAULTY ASSETS (Top 10)
    $stmt = $db->query("SELECT a.name, a.asset_tag, a.category, COUNT(r.request_id) as fault_count,
                        MAX(r.reported_at) as last_fault
                        FROM assets a 
                        LEFT JOIN maintenance_requests r ON a.asset_id = r.asset_id 
                        WHERE r.request_id IS NOT NULL
                        GROUP BY a.asset_id 
                        ORDER BY fault_count DESC 
                        LIMIT 10");
    $knowledge['top_faulty_assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // XIII. ASSETS IN USER'S DEPARTMENT
    if ($user_role === 'Staff' && !empty($user_department)) {
        $stmt = $db->prepare("SELECT name, category, status, asset_tag, model 
                              FROM assets 
                              WHERE location = ? 
                              ORDER BY name ASC");
        $stmt->execute([$user_department]);
        $knowledge['my_department_assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count by status in user's department
        $stmt = $db->prepare("SELECT status, COUNT(*) as count 
                              FROM assets 
                              WHERE location = ? 
                              GROUP BY status");
        $stmt->execute([$user_department]);
        $knowledge['my_department_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // XIV. TECHNICIAN PERFORMANCE
    $stmt = $db->query("SELECT 
                        CONCAT(u.first_name, ' ', u.last_name) as tech_name,
                        COUNT(r.request_id) as total_tasks,
                        SUM(CASE WHEN r.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as completed,
                        ROUND((SUM(CASE WHEN r.status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) / NULLIF(COUNT(r.request_id), 0)) * 100, 1) as completion_rate,
                        MAX(r.resolved_at) as last_resolved
                        FROM users u
                        LEFT JOIN maintenance_requests r ON u.user_id = r.assigned_to
                        WHERE u.role = 'ICT Technician'
                        GROUP BY u.user_id
                        ORDER BY completed DESC");
    $knowledge['tech_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // XV. SYSTEM OVERVIEW
    $knowledge['system_overview'] = [
        'total_requests' => $knowledge['maintenance_stats']['total_requests'] ?? 0,
        'pending_count' => $knowledge['maintenance_stats']['pending'] ?? 0,
        'resolution_rate' => $knowledge['maintenance_stats']['resolution_rate'] ?? 0,
        'total_assets' => $knowledge['total_assets'],
        'total_users' => $knowledge['users_summary']['total'] ?? 0,
        'active_users' => $knowledge['users_summary']['active'] ?? 0
    ];
    
    return $knowledge;
}

// ============================================================
// SECTION II: MAINTENANCE TIPS & GUIDES (ADVANCED)
// ============================================================
function getMaintenanceTips() {
    return [
        'printer' => [
            'en' => "**🖨️ PRINTER MAINTENANCE MASTER GUIDE**\n\n" .
                    "**I. DAILY MAINTENANCE**\n" .
                    "   • Keep paper trays clean and dust-free\n" .
                    "   • Check paper levels and quality\n" .
                    "   • Ensure proper ventilation around printer\n\n" .
                    "**II. WEEKLY MAINTENANCE**\n" .
                    "   • Run printer self-test page\n" .
                    "   • Clean exterior with soft cloth\n" .
                    "   • Check for error messages\n\n" .
                    "**III. MONTHLY MAINTENANCE**\n" .
                    "   1. Clean print heads using built-in utility\n" .
                    "   2. Check and clean rollers\n" .
                    "   3. Update printer drivers\n" .
                    "   4. Check ink/toner levels\n" .
                    "   5. Clean paper feed rollers\n\n" .
                    "**IV. QUARTERLY MAINTENANCE**\n" .
                    "   • Deep clean internal components\n" .
                    "   • Replace worn parts\n" .
                    "   • Check firmware updates\n" .
                    "   • Calibrate color settings\n\n" .
                    "**⚠️ TROUBLESHOOTING TIPS**\n" .
                    "   • Paper jams: Gently remove jammed paper\n" .
                    "   • Poor print quality: Clean print heads\n" .
                    "   • Slow printing: Check connection and drivers\n" .
                    "   • Error codes: Refer to user manual",
            
            'sw' => "**🖨️ MWONGOZO KAMILI WA MATENGENEZO YA PRINTER**\n\n" .
                    "**I. MATENGENEZO YA KILA SIKU**\n" .
                    "   • Weka trei za karatasi safi bila vumbi\n" .
                    "   • Angalia kiwango na ubora wa karatasi\n" .
                    "   • Hakikisha uingizaji hewa wa kutosha\n\n" .
                    "**II. MATENGENEZO YA KILA WIKI**\n" .
                    "   • Chapisha ukurasa wa majaribio\n" .
                    "   • Safisha sehemu za nje kwa kitambaa laini\n" .
                    "   • Angalia ujumbe wa hitilafu\n\n" .
                    "**III. MATENGENEZO YA KILA MWEZI**\n" .
                    "   1. Safisha vichwa vya uchapishaji\n" .
                    "   2. Angalia na safisha rola\n" .
                    "   3. Sasisha viendeshaji vya printer\n" .
                    "   4. Angalia kiwango cha wino/tona\n" .
                    "   5. Safisha rola za kulisha karatasi\n\n" .
                    "**IV. MATENGENEZO YA KILA ROBOTU**\n" .
                    "   • Safisha vipengele vya ndani kabisa\n" .
                    "   • Badilisha sehemu zilizochakaa\n" .
                    "   • Angalia sasisho za firmware\n" .
                    "   • Rekebisha mipangilio ya rangi\n\n" .
                    "**⚠️ VIDOKEZO VYA KUTATUA MATATIZO**\n" .
                    "   • Mkungo wa karatasi: Ondoa karatasi kwa upole\n" .
                    "   • Ubora duni wa uchapishaji: Safisha vichwa\n" .
                    "   • Uchapishaji wa polepole: Angalia muunganisho\n" .
                    "   • Nambari za hitilafu: Rejelea mwongozo"
        ],
        
        'laptop' => [
            'en' => "**💻 LAPTOP MAINTENANCE MASTER GUIDE**\n\n" .
                    "**I. DAILY MAINTENANCE**\n" .
                    "   • Keep laptop on hard, flat surface\n" .
                    "   • Avoid eating/drinking near laptop\n" .
                    "   • Use gentle touch on keyboard\n\n" .
                    "**II. WEEKLY MAINTENANCE**\n" .
                    "   • Clean screen with microfiber cloth\n" .
                    "   • Check for software updates\n" .
                    "   • Scan for viruses\n\n" .
                    "**III. MONTHLY MAINTENANCE**\n" .
                    "   1. Clean keyboard and touchpad\n" .
                    "   2. Check battery health\n" .
                    "   3. Clear temporary files\n" .
                    "   4. Update drivers\n" .
                    "   5. Check disk space\n\n" .
                    "**IV. QUARTERLY MAINTENANCE**\n" .
                    "   • Clean cooling vents\n" .
                    "   • Backup important data\n" .
                    "   • Check for hardware issues\n" .
                    "   • Optimize startup programs\n\n" .
                    "**⚠️ TROUBLESHOOTING TIPS**\n" .
                    "   • Slow performance: Clear cache and temp files\n" .
                    "   • Battery draining fast: Check power settings\n" .
                    "   • Overheating: Clean vents, use cooling pad\n" .
                    "   • Wi-Fi issues: Restart router, update drivers\n" .
                    "   • Blue screen: Check for driver conflicts",
            
            'sw' => "**💻 MWONGOZO KAMILI WA MATENGENEZO YA LAPTOP**\n\n" .
                    "**I. MATENGENEZO YA KILA SIKU**\n" .
                    "   • Weka laptop kwenye uso mgumu na sawa\n" .
                    "   • Epuka kula/kunywa karibu na laptop\n" .
                    "   • Tumia vibonyezo kwa upole\n\n" .
                    "**II. MATENGENEZO YA KILA WIKI**\n" .
                    "   • Safisha skrini kwa kitambaa cha microfiber\n" .
                    "   • Angalia sasisho za programu\n" .
                    "   • Fanya skeni ya virusi\n\n" .
                    "**III. MATENGENEZO YA KILA MWEZI**\n" .
                    "   1. Safisha kibodi na touchpad\n" .
                    "   2. Angalia afya ya betri\n" .
                    "   3. Ondoa faili za muda\n" .
                    "   4. Sasisha viendeshaji\n" .
                    "   5. Angalia nafasi ya diski\n\n" .
                    "**IV. MATENGENEZO YA KILA ROBOTU**\n" .
                    "   • Safisha matundu ya kupoeza\n" .
                    "   • Hifadhi nakala za data muhimu\n" .
                    "   • Angalia matatizo ya vifaa\n" .
                    "   • Boresha programu zinazoanza\n\n" .
                    "**⚠️ VIDOKEZO VYA KUTATUA MATATIZO**\n" .
                    "   • Utendaji wa polepole: Ondoa faili za muda\n" .
                    "   • Betri inakwisha haraka: Angalia mipangilio ya nishati\n" .
                    "   • Joto kupita kiasi: Safisha matundu\n" .
                    "   • Wi-Fi inashindwa: Washa-upya router\n" .
                    "   • Skrini ya buluu: Angalia migongano ya viendeshaji"
        ],
        
        'projector' => [
            'en' => "**📽️ PROJECTOR MAINTENANCE MASTER GUIDE**\n\n" .
                    "**I. DAILY MAINTENANCE**\n" .
                    "   • Allow proper cool-down time\n" .
                    "   • Turn off when not in use\n" .
                    "   • Keep in dust-free environment\n\n" .
                    "**II. WEEKLY MAINTENANCE**\n" .
                    "   • Clean lens with microfiber cloth\n" .
                    "   • Check air vents for dust\n" .
                    "   • Verify image quality\n\n" .
                    "**III. MONTHLY MAINTENANCE**\n" .
                    "   1. Clean or replace air filters\n" .
                    "   2. Check lamp hours\n" .
                    "   3. Clean projector body\n" .
                    "   4. Verify focus and alignment\n" .
                    "   5. Check cables and connections\n\n" .
                    "**IV. QUARTERLY MAINTENANCE**\n" .
                    "   • Deep clean internal components\n" .
                    "   • Replace lamp if needed\n" .
                    "   • Calibrate color settings\n" .
                    "   • Update firmware\n\n" .
                    "**⚠️ TROUBLESHOOTING TIPS**\n" .
                    "   • No image: Check connections and power\n" .
                    "   • Blurry image: Adjust focus lens\n" .
                    "   • Color issues: Check cables and settings\n" .
                    "   • Overheating: Clean filters and vents\n" .
                    "   • Flickering: Replace lamp",
            
            'sw' => "**📽️ MWONGOZO KAMILI WA MATENGENEZO YA PROJECTOR**\n\n" .
                    "**I. MATENGENEZO YA KILA SIKU**\n" .
                    "   • Ruhusu muda wa kutosha wa kupoa\n" .
                    "   • Zima wakati haitumiki\n" .
                    "   • Weka katika mazingira bila vumbi\n\n" .
                    "**II. MATENGENEZO YA KILA WIKI**\n" .
                    "   • Safisha lenzi kwa kitambaa cha microfiber\n" .
                    "   • Angalia matundu ya hewa\n" .
                    "   • Thibitisha ubora wa picha\n\n" .
                    "**III. MATENGENEZO YA KILA MWEZI**\n" .
                    "   1. Safisha au badilisha vichungi vya hewa\n" .
                    "   2. Angalia saa za taa\n" .
                    "   3. Safisha mwili wa projector\n" .
                    "   4. Thibitisha mwelekeo na mpangilio\n" .
                    "   5. Angalia nyaya na viunganisho\n\n" .
                    "**IV. MATENGENEZO YA KILA ROBOTU**\n" .
                    "   • Safisha vipengele vya ndani kabisa\n" .
                    "   • Badilisha taa ikiwa inahitajika\n" .
                    "   • Rekebisha mipangilio ya rangi\n" .
                    "   • Sasisha firmware\n\n" .
                    "**⚠️ VIDOKEZO VYA KUTATUA MATATIZO**\n" .
                    "   • Hakuna picha: Angalia viunganisho\n" .
                    "   • Picha haijulikani: Rekebisha lenzi\n" .
                    "   • Matatizo ya rangi: Angalia nyaya\n" .
                    "   • Joto kupita kiasi: Safisha vichungi\n" .
                    "   • Picha inayomehemehe: Badilisha taa"
        ],
        
        'network' => [
            'en' => "**🌐 NETWORK MAINTENANCE MASTER GUIDE**\n\n" .
                    "**I. DAILY MAINTENANCE**\n" .
                    "   • Monitor network performance\n" .
                    "   • Check for unusual traffic\n" .
                    "   • Verify all devices connected\n\n" .
                    "**II. WEEKLY MAINTENANCE**\n" .
                    "   • Review system logs\n" .
                    "   • Check bandwidth usage\n" .
                    "   • Verify security status\n\n" .
                    "**III. MONTHLY MAINTENANCE**\n" .
                    "   1. Update firmware on all devices\n" .
                    "   2. Check cable integrity\n" .
                    "   3. Clean equipment dust\n" .
                    "   4. Verify backup configurations\n" .
                    "   5. Test UPS battery\n\n" .
                    "**IV. QUARTERLY MAINTENANCE**\n" .
                    "   • Full security audit\n" .
                    "   • Network optimization\n" .
                    "   • Document all changes\n" .
                    "   • Test disaster recovery\n\n" .
                    "**⚠️ TROUBLESHOOTING TIPS**\n" .
                    "   • Slow network: Check bandwidth usage\n" .
                    "   • Intermittent connection: Check cables\n" .
                    "   • Cannot connect: Restart router/switch\n" .
                    "   • Security issues: Check firewall settings",
            
            'sw' => "**🌐 MWONGOZO KAMILI WA MATENGENEZO YA NETWORK**\n\n" .
                    "**I. MATENGENEZO YA KILA SIKU**\n" .
                    "   • Fuata utendaji wa mtandao\n" .
                    "   • Angalia trafiki isiyo ya kawaida\n" .
                    "   • Thibitisha vifaa vyote vimeunganishwa\n\n" .
                    "**II. MATENGENEZO YA KILA WIKI**\n" .
                    "   • Kagua kumbukumbu za mfumo\n" .
                    "   • Angalia matumizi ya bandwidth\n" .
                    "   • Thibitisha hali ya usalama\n\n" .
                    "**III. MATENGENEZO YA KILA MWEZI**\n" .
                    "   1. Sasisha firmware kwenye vifaa vyote\n" .
                    "   2. Angalia ubora wa nyaya\n" .
                    "   3. Safisha vumbi kwenye vifaa\n" .
                    "   4. Thibitisha mipangilio ya backup\n" .
                    "   5. Jaribu betri ya UPS\n\n" .
                    "**IV. MATENGENEZO YA KILA ROBOTU**\n" .
                    "   • Ukaguzi kamili wa usalama\n" .
                    "   • Uboreshaji wa mtandao\n" .
                    "   • Andika mabadiliko yote\n" .
                    "   • Jaribu urejeshaji wa maafa\n\n" .
                    "**⚠️ VIDOKEZO VYA KUTATUA MATATIZO**\n" .
                    "   • Mtandao wa polepole: Angalia bandwidth\n" .
                    "   • Muunganisho unaokatika: Angalia nyaya\n" .
                    "   • Haiwezi kuunganisha: Washa-upya router\n" .
                    "   • Maswala ya usalama: Angalia firewall"
        ],
        
        'desktop' => [
            'en' => "**🖥️ DESKTOP MAINTENANCE MASTER GUIDE**\n\n" .
                    "**I. DAILY MAINTENANCE**\n" .
                    "   • Keep work area clean\n" .
                    "   • Avoid blocking ventilation\n" .
                    "   • Use surge protector\n\n" .
                    "**II. WEEKLY MAINTENANCE**\n" .
                    "   • Run disk cleanup\n" .
                    "   • Check for updates\n" .
                    "   • Scan for malware\n\n" .
                    "**III. MONTHLY MAINTENANCE**\n" .
                    "   1. Defragment hard drive\n" .
                    "   2. Clean keyboard and mouse\n" .
                    "   3. Check power supply\n" .
                    "   4. Clean dust from fans\n" .
                    "   5. Check cable organization\n\n" .
                    "**IV. QUARTERLY MAINTENANCE**\n" .
                    "   • Deep clean internal components\n" .
                    "   • Backup all important data\n" .
                    "   • Check hardware performance\n" .
                    "   • Update BIOS if needed\n\n" .
                    "**⚠️ TROUBLESHOOTING TIPS**\n" .
                    "   • Won't start: Check power supply\n" .
                    "   • Slow: Clear temp files, defrag\n" .
                    "   • Loud fan: Clean dust from fans\n" .
                    "   • No display: Check cable connections",
            
            'sw' => "**🖥️ MWONGOZO KAMILI WA MATENGENEZO YA DESKTOP**\n\n" .
                    "**I. MATENGENEZO YA KILA SIKU**\n" .
                    "   • Weka eneo la kazi safi\n" .
                    "   • Epuka kuziba uingizaji hewa\n" .
                    "   • Tumia kinga ya umeme\n\n" .
                    "**II. MATENGENEZO YA KILA WIKI**\n" .
                    "   • Fanya usafi wa diski\n" .
                    "   • Angalia sasisho\n" .
                    "   • Fanya skeni ya virusi\n\n" .
                    "**III. MATENGENEZO YA KILA MWEZI**\n" .
                    "   1. Panga data kwenye diski\n" .
                    "   2. Safisha kibodi na mouse\n" .
                    "   3. Angalia usambazaji wa nguvu\n" .
                    "   4. Ondoa vumbi kwenye feni\n" .
                    "   5. Panga nyaya\n\n" .
                    "**IV. MATENGENEZO YA KILA ROBOTU**\n" .
                    "   • Safisha vipengele vya ndani kabisa\n" .
                    "   • Hifadhi nakala za data zote\n" .
                    "   • Angalia utendaji wa vifaa\n" .
                    "   • Sasisha BIOS ikiwa inahitajika\n\n" .
                    "**⚠️ VIDOKEZO VYA KUTATUA MATATIZO**\n" .
                    "   • Haianzi: Angalia usambazaji wa nguvu\n" .
                    "   • Polepole: Ondoa faili za muda\n" .
                    "   • Feni yenye kelele: Safisha vumbi\n" .
                    "   • Hakuna onyesho: Angalia nyaya"
        ]
    ];
}

// ============================================================
// SECTION III: KNOWLEDGE BASE
// ============================================================
function getKnowledgeBase() {
    return [
        'en' => "**📚 IFM ICT ASSET MANAGEMENT KNOWLEDGE BASE**\n\n" .
                "**I. SYSTEM OVERVIEW**\n" .
                "   • The system manages all ICT assets at IFM\n" .
                "   • Three user roles: Admin, Technician, Staff\n" .
                "   • QR codes are used for quick asset identification\n\n" .
                "**II. ASSET MANAGEMENT**\n" .
                "   • Assets are tracked by unique asset tags\n" .
                "   • Each asset has a QR code for easy scanning\n" .
                "   • Assets can be assigned to staff members\n" .
                "   • Status tracking: Available, In Use, Maintenance, Retired\n\n" .
                "**III. FAULT REPORTING**\n" .
                "   • Staff can report faults on any asset\n" .
                "   • Faults are assigned to technicians\n" .
                "   • Technicians update progress until resolved\n" .
                "   • Resolution rate is tracked for reporting\n\n" .
                "**IV. MAINTENANCE SCHEDULE**\n" .
                "   • Regular maintenance extends asset life\n" .
                "   • Preventive maintenance reduces downtime\n" .
                "   • Keep maintenance logs for each asset\n" .
                "   • Schedule next maintenance dates\n\n" .
                "**V. SYSTEM ROLES**\n" .
                "   • **System Administrator**: Full system control\n" .
                "   • **ICT Technician**: Handle maintenance tasks\n" .
                "   • **Staff**: Report faults, view assets\n\n" .
                "**VI. BEST PRACTICES**\n" .
                "   • Regular backups of system data\n" .
                "   • Keep software and firmware updated\n" .
                "   • Train users on proper asset use\n" .
                "   • Document all maintenance activities\n" .
                "   • Regular security audits",
        
        'sw' => "**📚 MSINGI WA MAARIFA YA IFM ICT ASSET MANAGEMENT**\n\n" .
                "**I. MUHTASARI WA MFUMO**\n" .
                "   • Mfumo unasimamia vifaa vyote vya ICT IFM\n" .
                "   • Majukumu matatu: Msimamizi, Fundi, Mfanyakazi\n" .
                "   • Misimbo ya QR inatumika kutambua vifaa haraka\n\n" .
                "**II. USIMAMIZI WA VIFAA**\n" .
                "   • Vifaa vinafuatiliwa kwa vibandiko vya kipekee\n" .
                "   • Kila kifaa kina QR code kwa skani rahisi\n" .
                "   • Vifaa vinaweza kukabidhiwa kwa wafanyakazi\n" .
                "   • Hali: Inapatikana, Inatumika, Matengenezo, Imestaafu\n\n" .
                "**III. KURIPOTI HITILAFU**\n" .
                "   • Wafanyakazi wanaweza kuripoti hitilafu\n" .
                "   • Hitilafu zinakabidhiwa kwa mafundi\n" .
                "   • Mafundi husasisha maendeleo hadi kutatuliwa\n" .
                "   • Kiwango cha utatuzi kinafuatiliwa\n\n" .
                "**IV. RATIBA YA MATENGENEZO**\n" .
                "   • Matengenezo ya mara kwa mara huongeza maisha ya vifaa\n" .
                "   • Matengenezo ya kuzuia hupunguza muda wa kukaa vibaya\n" .
                "   • Weka kumbukumbu za matengenezo kwa kila kifaa\n" .
                "   • Pangia tarehe za matengenezo yajayo\n\n" .
                "**V. MAJUKUMU YA MFUMO**\n" .
                "   • **Msimamizi wa Mfumo**: Udhibiti kamili\n" .
                "   • **Fundi wa ICT**: Kushughulikia matengenezo\n" .
                "   • **Mfanyakazi**: Kuripoti hitilafu, kuona vifaa\n\n" .
                "**VI. MIKAADA BORA**\n" .
                "   • Hifadhi nakala za data mara kwa mara\n" .
                "   • Sasisha programu na firmware\n" .
                "   • Fundisha watumiaji matumizi sahihi\n" .
                "   • Andika shughuli zote za matengenezo\n" .
                "   • Ukaguzi wa usalama wa mara kwa mara"
    ];
}

// ============================================================
// SECTION IV: SAVE CHAT HISTORY
// ============================================================
function saveChatHistory($db, $user_id, $session_id, $question, $answer, $language) {
    $stmt = $db->prepare("INSERT INTO chat_history (user_id, session_id, question, answer, language) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $session_id, $question, $answer, $language]);
}

// ============================================================
// SECTION V: BUILD SYSTEM PROMPT (ADVANCED)
// ============================================================
function buildSystemPrompt($knowledge, $user_role, $user_department, $user_name) {
    $tips = getMaintenanceTips();
    $knowledgeBase = getKnowledgeBase();
    
    $prompt = "**🏛️ IFM ICT ASSISTANCE - SYSTEM KNOWLEDGE**\n\n" .
              "**I. INSTITUTION INFORMATION**\n" .
              "   • Institution: {$knowledge['institution']['name']}\n" .
              "   • Motto: {$knowledge['institution']['motto']}\n" .
              "   • Department: {$knowledge['institution']['department']}\n" .
              "   • System: {$knowledge['institution']['system']}\n" .
              "   • Purpose: {$knowledge['institution']['purpose']}\n" .
              "   • Vision: {$knowledge['institution']['vision']}\n" .
              "   • Mission: {$knowledge['institution']['mission']}\n\n" .
              
              "**II. SYSTEM DATA OVERVIEW**\n" .
              "   📊 **Total Assets**: {$knowledge['total_assets']}\n" .
              "   👥 **Total Users**: {$knowledge['users_summary']['total']}\n" .
              "   ✅ **Active Users**: {$knowledge['users_summary']['active']}\n" .
              "   ❌ **Inactive Users**: {$knowledge['users_summary']['inactive']}\n" .
              "   📋 **Total Requests**: {$knowledge['maintenance_stats']['total_requests']}\n" .
              "   📈 **Resolution Rate**: {$knowledge['maintenance_stats']['resolution_rate']}%\n\n" .
              
              "**III. USER CONTEXT**\n" .
              "   👤 **User**: $user_name\n" .
              "   🎯 **Role**: $user_role\n" .
              "   🏢 **Department**: " . ($user_department ?: 'Not assigned') . "\n\n" .
              
              "**IV. ASSETS BY STATUS**\n" .
              implode("\n", array_map(function($s) {
                  return "   • {$s['status']}: {$s['count']} assets ({$s['percentage']}%)";
              }, $knowledge['assets_by_status'])) . "\n\n" .
              
              "**V. ASSETS BY DEPARTMENT**\n" .
              implode("\n", array_map(function($d) {
                  return "   • {$d['location']}: {$d['count']} assets (Available: {$d['available']}, In Use: {$d['in_use']}, Maintenance: {$d['under_maintenance']})";
              }, $knowledge['assets_by_department'])) . "\n\n" .
              
              "**VI. USERS BY ROLE**\n" .
              implode("\n", array_map(function($r) {
                  return "   • {$r['role']}: {$r['count']} users ({$r['active']} active, {$r['inactive']} inactive)";
              }, $knowledge['users_by_role'])) . "\n\n" .
              
              "**VII. MAINTENANCE STATISTICS**\n" .
              "   📝 Pending: {$knowledge['maintenance_stats']['pending']}\n" .
              "   📌 Assigned: {$knowledge['maintenance_stats']['assigned']}\n" .
              "   🔄 In Progress: {$knowledge['maintenance_stats']['in_progress']}\n" .
              "   ✅ Resolved: {$knowledge['maintenance_stats']['resolved']}\n" .
              "   🔒 Closed: {$knowledge['maintenance_stats']['closed']}\n\n" .
              
              "**VIII. REQUESTS BY PRIORITY**\n" .
              implode("\n", array_map(function($p) {
                  return "   • {$p['priority']}: {$p['count']} requests ({$p['percentage']}%)";
              }, $knowledge['requests_by_priority'])) . "\n\n" .
              
              "**IX. TOP 10 FAULTY ASSETS**\n" .
              implode("\n", array_map(function($a, $i) {
                  $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                  return "   {$roman[$i]}. {$a['name']} - {$a['fault_count']} faults (Last: {$a['last_fault']})";
              }, $knowledge['top_faulty_assets'], array_keys($knowledge['top_faulty_assets']))) . "\n\n" .
              
              "**X. TECHNICIAN PERFORMANCE**\n" .
              implode("\n", array_map(function($t) {
                  return "   • {$t['tech_name']}: {$t['total_tasks']} tasks, {$t['completed']} completed ({$t['completion_rate']}%)";
              }, $knowledge['tech_performance'])) . "\n\n" .
              
              "**XI. MAINTENANCE GUIDES**\n\n" .
              "   **A. PRINTER MAINTENANCE**\n" .
              "   {$tips['printer']['en']}\n\n" .
              "   **B. LAPTOP MAINTENANCE**\n" .
              "   {$tips['laptop']['en']}\n\n" .
              "   **C. PROJECTOR MAINTENANCE**\n" .
              "   {$tips['projector']['en']}\n\n" .
              "   **D. NETWORK MAINTENANCE**\n" .
              "   {$tips['network']['en']}\n\n" .
              "   **E. DESKTOP MAINTENANCE**\n" .
              "   {$tips['desktop']['en']}\n\n" .
              
              "**XII. KNOWLEDGE BASE**\n" .
              "{$knowledgeBase['en']}\n\n" .
              
              "**📋 RESPONSE GUIDELINES**\n" .
              "   I. Always respond in the SAME LANGUAGE as the user (EN or SW)\n" .
              "   II. Use proper formatting: bold (**), italics (*), emojis, and bullet points\n" .
              "   III. Structure responses with clear sections and headings\n" .
              "   IV. Provide specific, actionable advice based on system data\n" .
              "   V. Be professional, friendly, and helpful\n" .
              "   VI. Never reveal passwords or sensitive personal information\n" .
              "   VII. For maintenance questions, provide relevant tips from the guides\n" .
              "   VIII. For staff, offer troubleshooting help and guidance\n" .
              "   IX. For technicians, provide detailed technical support\n" .
              "   X. For admins, offer system insights and management advice\n" .
              "   XI. Suggest actions users can take (report fault, check status, etc.)\n" .
              "   XII. If unsure, say so honestly and offer to help find the answer\n\n" .
              
              "**🎯 REMEMBER**\n" .
              "   • You represent the IFM ICT Department\n" .
              "   • Be professional and helpful at all times\n" .
              "   • Provide accurate, data-driven responses\n" .
              "   • Use emojis to make responses engaging\n" .
              "   • Format responses beautifully for readability";

    // Add department-specific assets if staff
    if ($user_role === 'Staff' && !empty($knowledge['my_department_assets'])) {
        $prompt .= "\n\n**📂 ASSETS IN YOUR DEPARTMENT ({$user_department})**\n";
        $status_counts = [];
        foreach ($knowledge['my_department_assets'] as $asset) {
            $status_counts[$asset['status']] = ($status_counts[$asset['status']] ?? 0) + 1;
        }
        foreach ($status_counts as $status => $count) {
            $prompt .= "   • {$status}: {$count} assets\n";
        }
        $prompt .= "\n   **List of assets:**\n";
        foreach ($knowledge['my_department_assets'] as $asset) {
            $icon = $asset['category'] === 'Laptop' ? '💻' : 
                    ($asset['category'] === 'Printer' ? '🖨️' : 
                    ($asset['category'] === 'Projector' ? '📽️' : 
                    ($asset['category'] === 'Network' ? '🌐' : '🖥️')));
            $status_icon = $asset['status'] === 'Available' ? '✅' : 
                          ($asset['status'] === 'In Use' ? '🔵' : 
                          ($asset['status'] === 'Under Maintenance' ? '🟡' : '🔴'));
            $prompt .= "   • {$icon} {$asset['name']} ({$asset['asset_tag']}) - {$status_icon} {$asset['status']}\n";
        }
    }

    return $prompt;
}

// ============================================================
// SECTION VI: HANDLE AJAX REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    header('Content-Type: application/json');
    
    $userMessage = trim($_POST['message']);
    $language = $_POST['language'] ?? 'en';
    $session_id = $_POST['session_id'] ?? session_id();
    
    if (empty($userMessage)) {
        echo json_encode(['error' => '❌ Please enter a question.']);
        exit;
    }
    
    // Get full system knowledge
    $knowledge = getFullSystemKnowledge($db, $user_role, $user_department);
    $systemPrompt = buildSystemPrompt($knowledge, $user_role, $user_department, $user_name);
    
    $payload = [
        'model' => $model,
        'temperature' => 0.7,
        'max_tokens' => 2048,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            ['role' => 'user', 'content' => $userMessage]
        ]
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 45
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo json_encode(['error' => '🌐 Network issue. Please try again.']);
        exit;
    }
    
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errorMsg = isset($data['error']['message']) ? $data['error']['message'] : "HTTP $httpCode";
        echo json_encode(['error' => "❌ API Error: $errorMsg"]);
        exit;
    }
    
    $data = json_decode($response, true);
    $answer = $data['choices'][0]['message']['content'] ?? '❌ Could not generate response.';
    
    // Save to chat history
    if ($user_id) {
        saveChatHistory($db, $user_id, $session_id, $userMessage, $answer, $language);
    }
    
    echo json_encode(['response' => $answer]);
    exit;
}

// ============================================================
// SECTION VII: HANDLE GET REQUEST (System Info)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $knowledge = getFullSystemKnowledge($db, $user_role, $user_department);
    echo json_encode([
        'success' => true,
        'institution' => $knowledge['institution'],
        'system_overview' => $knowledge['system_overview'],
        'stats' => [
            'total_assets' => $knowledge['total_assets'],
            'total_users' => $knowledge['users_summary']['total'],
            'maintenance' => $knowledge['maintenance_stats'],
            'resolution_rate' => $knowledge['maintenance_stats']['resolution_rate'] . '%'
        ],
        'message' => '✅ ICT Assistance API is ready and fully loaded with system knowledge!'
    ]);
    exit;
}
