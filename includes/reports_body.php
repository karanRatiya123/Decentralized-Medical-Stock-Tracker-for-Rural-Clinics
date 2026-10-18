<?php
/* Shared Reports body - used by Staff, District Admin and System Admin.
   Set before including:
     $report_role   'staff' | 'district' | 'system'
     $report_scopes array of [value => label]  (one item = fixed, no dropdown)  */
$report_role   = $report_role   ?? 'staff';
$report_scopes = $report_scopes ?? ['own' => 'My PHC'];
?>
<div class="rl" id="rlRoot" data-role="<?php echo htmlspecialchars($report_role); ?>">

    <!-- Filters -->
    <div class="rl-bar">
        <div class="rl-field">
            <label for="rlScope">Scope</label>
            <?php if (count($report_scopes) > 1): ?>
            <select id="rlScope" class="rl-select">
                <?php foreach ($report_scopes as $v => $label): ?>
                <option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <span class="rl-pill" id="rlScope" data-value="<?php echo htmlspecialchars(array_key_first($report_scopes)); ?>"><i class="fas fa-hospital"></i> <?php echo htmlspecialchars(reset($report_scopes)); ?></span>
            <?php endif; ?>
        </div>
        <div class="rl-field">
            <label>Period</label>
            <div class="rl-seg" id="rlPeriod" role="group" aria-label="Period">
                <button type="button" data-p="today">Today</button>
                <button type="button" data-p="7d" class="on">7 days</button>
                <button type="button" data-p="30d">30 days</button>
                <button type="button" data-p="90d">3 months</button>
                <button type="button" data-p="year">Year</button>
            </div>
        </div>
        <div class="rl-field rl-live-wrap">
            <button type="button" class="rl-live on" id="rlLive" aria-pressed="true"><span class="rl-dot"></span><span id="rlLiveText">Live</span></button>
            <small id="rlStamp">--:--:--</small>
        </div>
    </div>

    <!-- Report options (click one) -->
    <div class="rl-catalog" id="rlCatalog" role="tablist" aria-label="Reports"></div>

    <!-- Selected report: analytics + graphs + download -->
    <section class="rl-detail" id="rlDetail" aria-live="polite">
        <div class="rl-dhead">
            <div class="rl-dtitle">
                <span class="rl-dicon" id="rlIcon"></span>
                <div>
                    <h3 id="rlTitle"></h3>
                    <p id="rlMeta"></p>
                </div>
            </div>
            <div class="rl-dl" id="rlDl">
                <button type="button" id="dlPdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="dlCsv"><i class="fas fa-file-csv"></i> CSV</button>
                <button type="button" id="dlPng"><i class="fas fa-image"></i> Charts (PNG)</button>
            </div>
        </div>
        <div class="rl-kpis" id="rlKpis"></div>
        <div class="rl-charts" id="rlCharts"></div>
        <div class="rl-tablewrap">
            <table class="rl-table" id="rlTable"></table>
        </div>
    </section>
</div>
