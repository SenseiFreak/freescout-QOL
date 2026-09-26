<a class="btn btn-default qol-add-calendar" href="{{ route('qol.calendar') }}" title="{{ __('Schedule this ticket') }}"><i class="glyphicon glyphicon-calendar"></i> {{ __('Add to calendar') }}</a>
<script>
(function () {
    function link() {
        var id = document.body.getAttribute('data-conversation_id');
        document.querySelectorAll('.qol-add-calendar').forEach(function (a) { if (id) a.href = a.href.split('?')[0] + '?ticket=' + encodeURIComponent(id); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', link); else link();
})();
</script>
