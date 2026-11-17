<?php
/**
 * Teaching dates of a semester: the first day, then how long teaching lasts,
 * either until a date or for a number of weeks (BUILD_SPEC 10.7). Used on
 * the Dates and holidays page and on the Create a semester form.
 * @var array $old     start_date, length_mode (end|weeks), end_date, weeks
 * @var array $errors
 * Optional: $optional (true on the create form: all fields may stay empty)
 */
$mode = ($old['length_mode'] ?? 'end') === 'weeks' ? 'weeks' : 'end';
$optional = !empty($optional);
?>
<div class="form-grid">
  <?= $this->partial('partials/field', ['name' => 'start_date', 'label' => 'First day of teaching' . ($optional ? ' (optional)' : ''), 'type' => 'date',
      'value' => $old['start_date'] ?? '', 'errors' => $errors, 'help' => 'For example the Monday lectures begin.']) ?>
</div>
<fieldset class="field length-choice" data-length-choice>
  <legend>How long does teaching last?</legend>
  <label><input type="radio" name="length_mode" value="end"<?= $mode === 'end' ? ' checked' : '' ?>> Until a date</label>
  <label><input type="radio" name="length_mode" value="weeks"<?= $mode === 'weeks' ? ' checked' : '' ?>> For a number of weeks</label>
</fieldset>
<div class="form-grid">
  <div data-length-for="end"<?= $mode === 'weeks' ? ' hidden' : '' ?>>
    <?= $this->partial('partials/field', ['name' => 'end_date', 'label' => 'Last day of teaching', 'type' => 'date', 'value' => $old['end_date'] ?? '',
        'errors' => $errors, 'help' => 'Usually the Friday before examinations.']) ?>
  </div>
  <div data-length-for="weeks"<?= $mode === 'end' ? ' hidden' : '' ?>>
    <?= $this->partial('partials/field', ['name' => 'weeks', 'label' => 'Number of weeks', 'type' => 'number', 'value' => $old['weeks'] ?? '',
        'errors' => $errors, 'min' => 1, 'max' => 52, 'help' => 'Counted from the first day, holidays included. We work out the last day for you (a Friday when it falls on a weekend).']) ?>
  </div>
</div>
