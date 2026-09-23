<button type="button" class="btn btn-default qol-bulk-merge" title="{{ __('Merge') }}">
    <span class="glyphicon glyphicon-indent-left"></span>
</button>

<div id="qol-bulk-merge-modal" class="hide">
    <div class="text-center">
        <p class="text-larger margin-top-10">{{ __('Merge selected conversations') }}</p>
        <p>{{ __('Choose the conversation to keep. The other selected conversations will be merged into it.') }}</p>
        <div class="form-group text-left">
            <label for="qol-bulk-merge-primary">{{ __('Keep this conversation') }}</label>
            <select id="qol-bulk-merge-primary" class="form-control"></select>
        </div>
        <div class="form-group margin-top">
            <button class="btn btn-primary qol-bulk-merge-confirm" data-loading-text="{{ __('Merging') }}…">{{ __('Merge') }}</button>
            <button class="btn btn-link" data-dismiss="modal">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>
