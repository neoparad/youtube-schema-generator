
<?php
/*
Plugin Name: YouTube Schema Generator
Description: YouTube動画の構造化データを自動生成
Version: 1.0.0
Author: LINKTH
License: GPL v2 or later
*/

// 更新チェッカーの読み込み
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// GitHubからの更新をチェックする設定
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/あなたのGitHubユーザー名/youtube-schema-generator/',
    __FILE__,
    'youtube-schema-generator'
);

// mainブランチを使用する設定
$myUpdateChecker->setBranch('main');

// 以下、既存のプラグインコード

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

class YouTube_Schema_Generator {
    private static $instance = null;
    
    // シングルトンパターンでインスタンスを取得
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 初期化処理
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('the_content', array($this, 'add_schema_to_content'));
    }

    public function register_settings() {
        register_setting('youtube_schema_options', 'youtube_schema_api_key');
    }

    public function add_admin_menu() {
        add_options_page(
            'YouTube Schema Generator Settings', // ページタイトル
            'YouTube Schema',                    // メニュータイトル
            'manage_options',                    // 必要な権限
            'youtube-schema-generator',          // メニューのスラッグ
            array($this, 'create_admin_page')    // 表示用の関数
        );
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h2>YouTube Schema Generator Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('youtube_schema_options');
                do_settings_sections('youtube_schema_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th>YouTube API Key</th>
                        <td>
                            <input type="text" 
                                   name="youtube_schema_api_key" 
                                   value="<?php echo esc_attr(get_option('youtube_schema_api_key')); ?>"
                                   class="regular-text">
                            <p class="description">
                                YouTube Data APIのキーを入力してください（オプション）
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_schema_to_content($content) {
        // YouTubeの動画URLを検出
        $pattern = '/<iframe[^>]*src=["\'](https?:\/\/www\.youtube\.com\/embed\/([^"\']+))["\'][^>]*>/i';
        
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[2] as $video_id) {
                // スキーマデータの生成
                $schema = $this->generate_video_schema($video_id);
                if ($schema) {
                    $content .= "\n" . $schema;
                }
            }
        }
        return $content;
    }

    private function generate_video_schema($video_id) {
        // 基本的なスキーマデータを生成
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => get_the_title(),
            'embedUrl' => "https://www.youtube.com/embed/{$video_id}",
            'thumbnailUrl' => "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg",
            'uploadDate' => get_the_date('c')
        );

        return '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}

// プラグインの初期化
function youtube_schema_generator_init() {
    YouTube_Schema_Generator::get_instance();
}
add_action('plugins_loaded', 'youtube_schema_generator_init');

// アクティベーション時の処理
register_activation_hook(__FILE__, 'youtube_schema_generator_activate');
function youtube_schema_generator_activate() {
    add_option('youtube_schema_api_key', '');
}

// 非アクティベーション時の処理
register_deactivation_hook(__FILE__, 'youtube_schema_generator_deactivate');
function youtube_schema_generator_deactivate() {
    // 必要に応じて設定を削除
}