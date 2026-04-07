<?php
global $hesk_settings, $hesklang;
/**
 * @var bool $customerLoggedIn - `true` if a customer is logged in, `false` otherwise
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
// UI-only demo mode (used for stakeholder screenshot mockups)
$uiDemo = isset($_GET['ui_demo']) && $_GET['ui_demo'] === '1';
define('EXTRA_PAGE_CLASSES','page-category-select');

define('ALERTS',1);

define('RENDER_COMMON_ELEMENTS',1);

global $BREADCRUMBS;
$BREADCRUMBS = array(
    array('url' => $hesk_settings['site_url'], 'title' => $hesk_settings['site_title']),
    array('url' => $hesk_settings['hesk_url'], 'title' => $hesk_settings['hesk_title']),
    array('title' => $uiDemo ? 'Надіслати звернення' : $hesklang['submit_ticket'])
);

/* Print header */
require_once(TEMPLATE_PATH . 'customer/inc/header.inc.php');
?>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php
                    hesk3_show_messages($messages);
                    ?>
                </div>
                <?php hesk3_show_messages($serviceMessages); ?>
                <h1 class="select__title">
                    <?php echo $uiDemo ? 'Чим ми можемо вам допомогти? Виберіть категорію звернення:' : $hesklang['select_category_text']; ?>
                </h1>
                <?php
                if ($uiDemo) {
                    $firstCategoryId = array_key_first($hesk_settings['categories'] ?? []);

                    $demoCats = [
                        [
                            'slug' => 'it-equipment',
                            'title' => 'IT-обладнання',
                            'descr' => 'Запити щодо нової техніки та обладнання.',
                            'icon' => 'icon-tools',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=it-equipment',
                            'other' => false,
                        ],
                        [
                            'slug' => 'event-support',
                            'title' => 'Техпідтримка подій',
                            'descr' => 'Технічна підготовка та супровід заходів, подій.',
                            'icon' => 'icon-calendar',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=event-support',
                            'other' => false,
                        ],
                        [
                            'slug' => 'printing',
                            'title' => 'Система друку',
                            'descr' => 'Питання щодо принтерів та сканерів.',
                            'icon' => 'icon-print',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=printing',
                            'other' => false,
                        ],
                        [
                            'slug' => 'wifi',
                            'title' => 'Інтернет з\'єднання / Wi-Fi',
                            'descr' => 'Проблеми з мережею та Wi-Fi.',
                            'icon' => 'icon-support',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=wifi',
                            'other' => false,
                        ],
                        [
                            'slug' => 'account-login',
                            'title' => 'Обліковий запис/Логування',
                            'descr' => 'Проблеми з входом та паролями.',
                            'icon' => 'icon-person',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=account-login',
                            'other' => false,
                        ],
                        [
                            'slug' => 'software-help',
                            'title' => 'Допомога з програмою',
                            'descr' => 'Тестування/консультації щодо роботи з ПЗ.',
                            'icon' => 'icon-support',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=software-help',
                            'other' => false,
                        ],
                        [
                            'slug' => 'hosting-sites',
                            'title' => 'Хостинг і сайти',
                            'descr' => 'Питання щодо веб-ресурсів та доменів.',
                            'icon' => 'icon-folder',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=hosting-sites',
                            'other' => false,
                        ],
                        [
                            'slug' => 'crm',
                            'title' => 'Система CRM УКУ',
                            'descr' => 'Технічна підтримка щодо роботи з CRM.',
                            'icon' => 'icon-inquiries',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=crm',
                            'other' => false,
                        ],
                        [
                            'slug' => 'lms-moodle',
                            'title' => 'LMS Moodle',
                            'descr' => 'Налаштування та підтримка курсів у Moodle.',
                            'icon' => 'icon-modules',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=lms-moodle',
                            'other' => false,
                        ],
                        [
                            'slug' => 'lms-academy-ocean',
                            'title' => 'LMS Academy Ocean',
                            'descr' => 'Доступ та виправлення проблем з курсами.',
                            'icon' => 'icon-templates',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=lms-academy-ocean',
                            'other' => false,
                        ],
                        [
                            'slug' => 'other',
                            'title' => 'Інше',
                            'descr' => 'Якщо ваша категорія відсутня у списку',
                            'icon' => 'icon-actions',
                            'href' => 'index.php?a=add&ui_demo=1&category=' . rawurlencode((string) $firstCategoryId) . '&demo_cat=other',
                            'other' => true,
                        ],
                    ];
                    ?>
                    <div class="nav nav--prototype-categories">
                        <?php foreach ($demoCats as $cat): ?>
                            <?php $cls = 'navlink'; if (!empty($cat['other'])) $cls .= ' navlink--other'; ?>
                            <a
                                href="<?php echo htmlspecialchars($cat['href'], ENT_QUOTES, 'UTF-8'); ?>"
                                class="<?php echo $cls; ?>"
                            >
                                <span class="icon-in-circle" aria-hidden="true">
                                    <svg class="icon <?php echo htmlspecialchars($cat['icon'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#<?php echo htmlspecialchars($cat['icon'], ENT_QUOTES, 'UTF-8'); ?>"></use>
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="navlink__title"><?php echo $cat['title']; ?></h3>
                                    <div class="navlink__descr"><?php echo $cat['descr']; ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php
                } else if (($category_count = count($hesk_settings['categories'])) > $hesk_settings['cat_show_select']): ?>
                    <form action="index.php" method="get">
                        <div style="display: table; margin: 40px auto;">
                            <select class="form-control cat-select" name="category" id="select_category" aria-label="<?php echo $hesklang['select_category']; ?>">
                                <?php
                                if ($hesk_settings['select_cat'])
                                {
                                    echo '<option value="">'.$hesklang['select'].'</option>';
                                }
                                foreach ($hesk_settings['categories'] as $k=>$v)
                                {
                                    echo '<option value="'.$k.'">'.$v['name'].'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-footer">
                            <button class="btn btn-full" type="submit"><?php echo $hesklang['c2c']; ?></button>
                            <input type="hidden" name="a" value="add">
                        </div>
                    </form>
                <?php else: ?>
                    <div class="nav nav--prototype-categories">
                        <?php foreach ($hesk_settings['categories'] as $k => $v): ?>
                        <a href="index.php?a=add&amp;category=<?php echo $k; ?>" class="navlink <?php if ($category_count > 8) echo "navlink-condensed"; ?>">
                            <span class="icon-in-circle" aria-hidden="true">
                                <svg class="icon icon-chevron-right">
                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                                </svg>
                            </span>
                            <div>
                                <h2 class="navlink__title"><!--[if IE]> &raquo; <![endif]--><?php echo $v['name']; ?></h2>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (document.getElementById('select_category')) {
            $('#select_category').selectize();
        }
    });
</script>
<?php
/* Print Footer */
require_once(TEMPLATE_PATH . 'customer/inc/footer.inc.php');
?>
