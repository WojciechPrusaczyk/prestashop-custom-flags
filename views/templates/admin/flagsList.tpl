<div class="row">
  <div class="col-md-12">
    <table class="table">
      <thead class="thead-default">
      <tr>
        <th>Name</th>
        <th>Displayed flag text</th>
        <th>Type</th>
        <th>Trigger</th>
      </tr>
      </thead>
      <tbody>
      {foreach from=$flags item=flag}
        <tr>
          <th>
            <span>{$flag.name}</span>
          </th>
          <th>
            <span>{$flag.display_text}</span>
          </th>
          <th>
            <span>{$flag.type}</span>
          </th>
          <th>
            <span>
                {if $flag.trigger_type != 'none'}
                    {$flag.trigger_type}&nbsp{$flag.trigger_operator}&nbsp{$flag.trigger_value}
                {/if}
            </span>
          </th>
          </th>
        </tr>
      {/foreach}
      {if $flags|@count === 0}
        <tr>
          <th class="text-center" colspan="4">No flags created yet</th>
        </tr>
      {/if}
      </tbody>
    </table>
  </div>
</div>
