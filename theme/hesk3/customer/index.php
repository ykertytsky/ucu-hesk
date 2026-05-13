<?php
global $hesk_settings, $hesklang;

/**
 * @var array $topArticles - Collection of top knowledgebase articles
 * @var array $latestArticles - Collection of newest/latest knowledgebase articles
 * @var array $serviceMessages - Collection of service messages to be displayed
 * @var array $messages - Collection of feedback messages to be displayed (such as "You have been logged out")
 * @var bool $accountRequired - `true` if an account is required to use the helpdesk, `false` otherwise
 * @var bool $customerLoggedIn - `true` if a customer is logged in, `false` otherwise
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */
// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
define('EXTRA_PAGE_CLASSES','page-index');

define('ALERTS',1);
define('KBSEARCH',1);
define('RATING',1);

define('OUTPUT_SEARCH_STYLING',1);
define('RENDER_COMMON_ELEMENTS',1);

global $BREADCRUMBS;
$BREADCRUMBS = array(
    array('url' => $hesk_settings['site_url'], 'title' => $hesk_settings['site_title']),
    array('title' => $hesk_settings['hesk_title'])
);

/* Print header */
require_once(TEMPLATE_PATH . 'customer/inc/header.inc.php');
?>
        <div class="main__content">
            <div class="contr">
                <div style="margin-bottom: 20px;">
                    <?php hesk3_show_messages($messages); ?>
                </div>
                <div class="help-search">
                    <h1 class="search__title">Привіт, як ми можемо допомогти?</h1>
                    <form action="#" method="get" style="display: inline; margin: 0;" name="searchform" onsubmit="return false;">
                        <div class="search__form">
                            <div class="form-group">
                                <button class="btn search__submit" type="button" aria-label="Пошук">
                                    <svg class="icon icon-search">
                                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-search"></use>
                                    </svg>
                                </button>
                                <input
                                    id="kb_search"
                                    name="search"
                                    class="form-control"
                                    type="text"
                                    aria-label="Пошук статей"
                                    placeholder="Пошук статей"
                                >
                            </div>
                            <div class="kb-suggestions boxed" style="display:none">
                                <h6>Підказки:</h6>
                                <ul id="kb-suggestion-list" class="type--list"></ul>
                            </div>
                        </div>
                    </form>
                </div>
                <?php hesk3_show_messages($serviceMessages); ?>
                <div class="nav nav--prototype-home">
                    <a href="index.php?a=add&ui_demo=1" class="navlink">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-submit-ticket">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-submit-ticket"></use>
                            </svg>
                        </span>
                        <div>
                            <h3 class="navlink__title">Надіслати звернення</h3>
                            <div class="navlink__descr">Повідомити про проблему чи питання</div>
                        </div>
                    </a>
                    <a href="ticket.php" class="navlink">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-document">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-document"></use>
                            </svg>
                        </span>
                        <div>
                            <h3 class="navlink__title">Переглянути існуюче звернення</h3>
                            <div class="navlink__descr">Перейдіть до свого наявного звернення</div>
                        </div>
                    </a>
                    <a href="knowledgebase.php" class="navlink">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-knowledge">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-knowledge"></use>
                            </svg>
                        </span>
                        <div>
                            <h3 class="navlink__title">База знань</h3>
                            <div class="navlink__descr">Пошук відповідей, інструкції та корисний контент</div>
                        </div>
                    </a>
                </div>
                <?php
                if (!$customerLoggedIn && $hesk_settings['alink']):
                ?>
                <div class="article__footer">
                    <a href="<?php echo $hesk_settings['admin_dir']; ?>/" class="link"><?php echo $hesklang['ap']; ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
<?php
/* Print Footer */
require_once(TEMPLATE_PATH . 'customer/inc/footer.inc.php');
?>
