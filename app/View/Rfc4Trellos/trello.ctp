<table class="table table-hover table-bordered table-striped">
    <thead>
        <tr>
            <th colspan="2" class="text-center success" style="vertical-align: middle;">Story</th>
            <th colspan="4" class="text-center success" style="vertical-align: middle;">Rough(doing)</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle;">Rough(Review)</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle;">Fill(doing)</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle;">Fill(Review)</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle;">Closing</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle;">Done</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle; min-width:80px;">サイクル<br />タイム</th>
            <th rowspan="2" class="text-center success" style="vertical-align: middle; min-width:60px;">係数</th>        
        </tr>
        <tr>
            <th class="text-center success" style="vertical-align: middle;">ID</th>
            <th class="text-center success" style="vertical-align: middle;">Title</th>
            <th class="text-center success" style="vertical-align: middle;">Due</th>
            <th class="text-center success" style="vertical-align: middle;">Start date</th>
            <th class="text-center success" style="vertical-align: middle;">Passed date</th>
            <th class="text-center success" style="vertical-align: middle;">Days</th>
        </tr>
    </thead>
    <tbody>
<?php
    if (! empty($data['rfc_card'])) {
    foreach($data['rfc_card'] as $rec) { ?>
    <tr>
        <td><?php echo h($rec['Card']['id']);?></td>
        <td><?php echo h($rec['Card']['title']);?></td>
        <td class="text-right"><?php echo h($rec['RoughDoing']['due']);?></td>
        <td><?php echo h($rec['RoughDoing']['startDate']);?></td>
        <td><?php echo h($rec['RoughDoing']['passDate']);?></td>
        <td <?php 
                echo ($rec['RoughDoing']['days'] - $rec['RoughDoing']['due'] >= 2)?'class="warning text-right"':'class="text-right"'; ?> >
            <?php echo h($rec['RoughDoing']['days']);?>
        </td>
        <td><?php echo h($rec['RoughReview']['date']);?></td>
        <td><?php echo h($rec['FillDoing']['date']);?></td>
        <td><?php echo h($rec['FillReview']['date']);?></td>      
        <td><?php echo h($rec['Closing']['date']);?></td>
        <td><?php echo h($rec['Done']['date']);?></td>
        <td class="text-right"><?php echo h($rec['CycleTime']['days']);?></td>
        <td <?php echo ($rec['Coefficient']['number'] >=4)?'class="warning text-right"':'class="text-right"'; ?>>
            <?php echo h($rec['Coefficient']['number']);?>
        </td>
    </tr>
<?php
    }
?>
    <tr> 
        <td colspan="12">
            &nbsp;
        </td>
        <td class="text-right" style="background-color:red">
              <strong>
                  <?php echo empty($data['average_coef']) ? 0 : h($data['average_coef']);?>
              </strong>
        </td>
    </tr>
<?php } ?>
</tbody>
</table>



