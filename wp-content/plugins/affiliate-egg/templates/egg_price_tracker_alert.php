<?php defined( '\ABSPATH' ) || exit;/*  Name: Price tracker & alert */__('Price tracker & alert', 'affegg-tpl');use Keywordrush\AffiliateEgg\TemplateHelper;?><?php $this->enqueueStyle(); ?><div class="egg-container cegg-price-tracker-item">    <div class="products1">        <?php foreach ($items as $item): ?>            <div class="row">                <div class="col-md-8">                    <h3 class="media-heading cegg-no-top-margin cegg-mb15" id="<?php echo esc_attr($item['id']); ?>"><?php echo $item['title']; ?><?php if ($item['manufacturer']): ?>, <?php echo esc_html($item['manufacturer']); ?><?php endif; ?></h3>                                        <?php if (!empty($item['extra']['rating'])): ?>                        <div class="cegg-mb15">                                    <?php echo TemplateHelper::printRating($item, 'small'); ?>                                                    </div>                    <?php endif; ?>                    <div class="panel panel-default cegg-price-tracker-panel cegg-mb10">                        <div class="panel-body">                            <div class="row" style="margin-bottom: 0px;">                                <div class="col-md-7 col-sm-7 col-xs-12 cegg-mb15">                                    <?php if ($item['price']): ?>                                        <span class="cegg-price">                                            <small><?php _e('Price', 'affegg-tpl'); ?>:</small> <span class="cegg-price-color"><?php echo TemplateHelper::formatPriceCurrency($item['price_raw'], $item['currency_code'], '<span class="cegg-currency">', '</span>'); ?></span>                                        </span>                                        <br><small class="text-muted"><?php _e('as of', 'affegg-tpl'); ?> <?php echo TemplateHelper::getLastUpdateFormatted($item['id'], false, false); ?></small>                                    <?php endif; ?>                                    &nbsp;                                </div>                                <div class="col-md-5 col-sm-5 col-xs-12 text-muted">                                    <a rel="nofollow" target="_blank" href="<?php echo $item['url']; ?>" class="btn btn-danger"<?php echo $item['ga_event'] ?>><?php _e('Buy now', 'affegg-tpl'); ?></a>                                                                        <?php if (!empty($item['domain'])): ?>                                        <div class="cegg-mb5">                                            <img src="<?php echo esc_attr(TemplateHelper::getMerhantIconUrl($item, false)); ?>" /> <small class="text-muted"><?php echo $item['domain']; ?></small>                                        </div>                                    <?php endif; ?>                                </div>                            </div>                        </div>                    </div>                    <?php $this->renderBlock('price_alert_inline', array('item' => $item)); ?>                </div>                                <div class="col-md-4">                    <?php if ($item['img']): ?>                        <div class="cegg-thumb">                            <a rel="nofollow" target="_blank" href="<?php echo $item['url']; ?>"<?php echo $item['ga_event'] ?>>                                <img src="<?php echo $item['img']; ?>" alt="<?php echo esc_attr($item['title']); ?>" />                            </a>                        </div>                    <?php endif; ?>                </div>            </div>            <div class="row">                <div class="col-md-12">                    <?php $this->renderBlock('price_history', array('item' => $item)); ?>                    <?php if ($item['description']): ?>                        <p><?php echo $item['description']; ?></p>                    <?php endif; ?>                </div>            </div>        <?php endforeach; ?>    </div></div>