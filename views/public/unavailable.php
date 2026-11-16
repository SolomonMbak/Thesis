<?php
/**
 * Neutral page for a school that does not exist, is suspended, or a grid
 * that is not in the published timetable. It never says which of these it is.
 * Optional: $notFound and $slug (grid not found), $moved (old single-school address)
 */
?>
<div class="card card-narrow">
  <?php if (!empty($moved)): ?>
    <h1>This address has moved</h1>
    <p>Each school now has its own timetable address, such as <code>/s/your-school</code>. Ask your school for its link.</p>
  <?php elseif (!empty($notFound)): ?>
    <h1>Timetable not found</h1>
    <p>That timetable is not part of the published timetable. It may have been changed.</p>
    <p><a class="btn btn-primary" href="<?= e(public_url($slug)) ?>">See all timetables of this school</a></p>
  <?php else: ?>
    <h1>Timetable not available</h1>
    <p>There is no timetable to show at this address. Check the link with your school.</p>
  <?php endif; ?>
</div>
