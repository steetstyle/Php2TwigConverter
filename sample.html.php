<div id="goal-detail-container">

    <?php if (empty($productGoal)) { ?>

        <div class="alert alert-info">
            <p>Hedef bilgisi bulunmamaktadır.</p>
        </div>

    <?php } else { ?>

        <?php if (!$parameters['monthly_view']) { ?>

            <table class="table table-bordered table-condensed table-striped">
                <thead>
                    <tr>
                        <th scope="col" width="15%">Ürün</th>
                        <th scope="col" width="15%">Üretim</th>
                        <th scope="col" width="15%">Hedef</th>
                        <th scope="col" width="60%">Gerçekleşme Yüzdesi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productGoal as $goal) { ?>

                        <?php
                            if (0 == $goal['amount_goal']) {
                                $percentage = 0;
                            } else {
                                $percentage = ($goal['production'] / $goal['amount_goal']) * 100;
                            }
                        ?>

                        <tr>
                            <td><?php echo $goal['name']; ?></td>
                            <td><?php echo $view['general']->formatNumber($goal['production']); ?> TL</td>
                            <td><?php echo $view['general']->formatNumber($goal['amount_goal']); ?> TL</td>
                            <td>
                                <div class="mb-2">
                                    <?php echo $view['general']->formatNumber($goal['production']); ?> TL / <?php echo $view['general']->formatNumber($goal['amount_goal']); ?> TL
                                </div>

                                <b class="text-primary">%<?php echo number_format($percentage, 2, ',', '.'); ?></b>

                                <div class="progress mt-4 mb-2">
                                    <div
                                        class="progress-bar <?php if ($percentage < 5) {
                                            echo 'bg-danger';
                                        } elseif ($percentage >= 6 && $percentage < 25) {
                                            echo 'bg-warning';
                                        } elseif ($percentage >= 25 && $percentage < 50) {
                                            echo 'bg-info';
                                        } elseif ($percentage >= 50 && $percentage >= 100) {
                                            echo 'bg-success';
                                        } ?>"
                                        style="width: <?php echo $percentage; ?>%"
                                        title="Oto dışı üretim hedef durumu %<?php echo $percentage; ?>"
                                        data-placement="top"
                                        data-toggle="tooltip"
                                    >
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

        <?php } else { ?>

            <?php if ($parameters['cumulative_view']) { ?>
                <h5 class="proposal-sub-title-modal mt-4">Toplam Üretim & Hedef Dağılımı</h5>
            <?php } else { ?>
                <?php if (!empty($parameters['product_id']) && empty($parameters['month_id'])) { ?>
                    <h5 class="proposal-sub-title-modal mt-4 product-title"></h5>
                <?php } else { ?>
                    <h5 class="proposal-sub-title-modal mt-4"><?php echo $view['general']->formatMonthTitle($parameters['month_id']); ?></h5>
                <?php } ?>
            <?php } ?>

            <table class="table table-bordered table-striped table-sticky">
                <thead class="sticky-table-header">
                    <tr>
                        <?php if (!$parameters['cumulative_view'] && (!empty($parameters['product_id']) && empty($parameters['month_id']))) { ?>
                            <th scope="col" width="20%" class="table-sticky-border fw-700">Ay</th>
                        <?php } else { ?>
                            <th scope="col" width="20%" class="table-sticky-border fw-700">Ürün</th>
                        <?php } ?>
                        <th scope="col" width="15%" class="table-sticky-border fw-700">Toplam Üretim</th>
                        <th scope="col" width="15%" class="table-sticky-border fw-700">Toplam Hedef</th>
                        <th scope="col" width="50%" class="table-sticky-border fw-700">Gerçekleşme Yüzdesi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($parameters['cumulative_view']) { ?>
                        <?php foreach ($productGoal['cumulativeData']['records'] as $data) { ?>
                            <?php echo $view->render('report/table-body.html.php', ['data' => $data]); ?>
                        <?php } ?>
                    <?php } else { ?>
                        <?php if (!empty($parameters['product_id']) && empty($parameters['month_id'])) { ?>
                            <?php foreach ($parameters['turkishMonths'] as $k => $m) { ?>
                                <?php foreach ($productGoal['data']['records'] as $d) { ?>
                                    <?php foreach ($d['monthly_data'] as $data) { ?>
                                        <?php if ($k == $data['month'] && $data['month'] <= date('m')) { ?>
                                            <?php echo $view->render('report/table-body.html.php', ['data' => $data, 'displayMonths' => true]); ?>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                            <?php } ?>
                        <?php } else { ?>
                            <?php foreach ($productGoal['data']['records'] as $d) { ?>
                                <?php foreach ($d['monthly_data'] as $data) { ?>
                                    <?php echo $view->render('report/table-body.html.php', ['data' => $data]); ?>
                                <?php } ?>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <?php if ($parameters['cumulative_view']) { ?>
                        <?php echo $view->render('report/table-foot.html.php', ['title' => 'GENEL TOPLAM', 'data' => $productGoal['cumulativeData']]); ?>
                    <?php } else { ?>
                        <?php if (empty($parameters['product_id']) && !empty($parameters['month_id'])) { ?>
                            <?php echo $view->render('report/table-foot.html.php', ['title' => 'AY TOPLAM', 'data' => $productGoal['data']]); ?>
                            <?php echo $view->render('report/table-foot.html.php', ['title' => 'GENEL TOPLAM', 'data' => $productGoal['cumulativeData']]); ?>
                        <?php } elseif (!empty($parameters['product_id']) && empty($parameters['month_id'])) { ?>
                            <?php echo $view->render('report/table-foot.html.php', ['title' => 'TOPLAM', 'data' => $productGoal['cumulativeData']]); ?>
                        <?php } else { ?>
                            <?php echo $view->render('report/table-foot.html.php', ['title' => 'GENEL TOPLAM', 'data' => $productGoal['cumulativeData']]); ?>
                        <?php } ?>
                    <?php } ?>
                </tfoot>
            </table>
        <?php } ?>
    <?php } ?>
</div>
