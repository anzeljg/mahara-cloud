{if $microheaders}{include file="viewmicroheader.tpl"}{else}{include file="header.tpl"}{/if}

        <h2>
            {if $viewid != 0}
            <a href="{$WWWROOT}view/view.php?id={$viewid}">{$viewtitle}</a>{if $ownername} {str tag=by section=view}
            <a href="{$WWWROOT}{$ownerlink}">{$ownername}</a>: {$data.name}{/if}
            {else}{$viewtitle}{/if}
        </h2>

        <div id="view">
        <h4>
            <div class="fl filedata-thumb">
                <a href="{$WWWROOT}artefact/cloud/blocktype/sugarsync/download.php?id={$id}&view={$viewid}">
                {if $type == 'folder'}
                <img alt="" src="{theme_url filename="images/folder.png"}">
                {else}
                <img alt="" src="{theme_url filename="images/file.png"}">
                {/if}
                </a>
            </div>
            <a href="{$WWWROOT}artefact/cloud/blocktype/sugarsync/download.php?id={$id}&view={$viewid}">{$data.name}</a>
        </h4>

        <table class="filedata">
            <tbody>
                <tr>
                    <th>{str tag=Description section=artefact.file}:</th>
                    <td style="vertical-align:middle">{$data.description}</td>
                </tr>
                <tr>
                    <th>{str tag=Created section=artefact.file}:</th>
                    <td style="vertical-align:middle">{$data.created}</td>
                </tr>
                <tr>
                    <th>{str tag=lastmodified section=artefact.file}:</th>
                    <td style="vertical-align:middle">{$data.updated}</td>
                </tr>
                {if $type != 'folder'}
                <tr>
                    <th>{str tag=Size section=artefact.file}:</th>
                    <td style="vertical-align:middle">{$data.size} ({$data.bytes} {str tag=bytes section=artefact.file})</td>
                </tr>
                {/if}
                <tr>
                    <th>{str tag=Shared section=artefact.cloud}:</th>
                    <td style="vertical-align:middle">{if $data.shared}<img alt="" src="{theme_url filename="images/success.png"}">{/if}</td>
                </tr>
                <tr>
                    <th>{str tag=Download section=artefact.file}:</th>
                    <td style="vertical-align:middle">
                    <a href="{$WWWROOT}artefact/cloud/blocktype/sugarsync/download.php?id={$id}&view={$viewid}">{str tag=Download section=artefact.file}</a>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>

{if $microheaders}{include file="microfooter.tpl"}{else}{include file="footer.tpl"}{/if}
