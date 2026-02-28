

<?php $__env->startSection('title', 'Доступность и цены'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <form method="get" action="<?php echo e(route('availability.index')); ?>">
        <div class="form-row">
            <div class="form-group">
                <label for="hotel_id">Отель</label>
                <select name="hotel_id" id="hotel_id" required>
                    <option value="">— Выберите отель —</option>
                    <?php $__currentLoopData = $hotels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($h->id); ?>" <?php if($h->id == $hotelId): echo 'selected'; endif; ?>><?php echo e($h->name); ?> <?php if($h->city): ?>(<?php echo e($h->city); ?>)<?php endif; ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_from">Дата с</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo e($dateFrom); ?>" required>
            </div>
            <div class="form-group">
                <label for="date_to">Дата по</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo e($dateTo); ?>" required>
            </div>
            <div class="form-group">
                <button type="submit">Показать</button>
            </div>
        </div>
    </form>
</div>

<?php if($error): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<?php if($hotelId > 0 && !$error): ?>
    <div class="card">
        <h2 style="margin:0 0 1rem; font-size:1.1rem;">Доступность и цены (данные из ClickHouse)</h2>
        <?php if(count($rows) === 0): ?>
            <p class="empty">За выбранный период записей нет.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Доступность</th>
                        <th>Цена</th>
                        <th>Валюта</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($row['date'] ?? ''); ?></td>
                            <td class="<?php echo e((int)($row['available'] ?? 0) === 1 ? 'available-yes' : 'available-no'); ?>">
                                <?php echo e((int)($row['available'] ?? 0) === 1 ? 'Да' : 'Нет'); ?>

                            </td>
                            <td><?php echo e($row['price'] ?? '—'); ?></td>
                            <td><?php echo e($row['currency'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/availability/index.blade.php ENDPATH**/ ?>