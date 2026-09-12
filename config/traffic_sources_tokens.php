<?php

return [

    'propellerads' => 'SUB_ID=${SUBID}'
        .'&campaign_id={campaign_id}'
        .'&country={country}'
        .'&region={region}'
        .'&language={language}'
        .'&device={device}'
        .'&os={osversion}'
        .'&browser={browser}'
        .'&browser_version={browser_version}'
        .'&connection_type={connection_type}'
        .'&carrier={carrier}'
        .'&isp={isp}'
        .'&zoneid={zoneid}'
        .'&subzone_id={subzone_id}'
        .'&cost={cost}'
        .'&useragent={useragent}'
        .'&user_activity={user_activity}',

    'hilltopads' => 'SUB_ID={{ctoken}}'
        .'&campaign_id={{campaignid}}'
        .'&country={{geo}}'
        .'&language={{lang}}'
        .'&os={{appname}}'
        .'&browser={{browsername}}'
        .'&zoneid={{zoneid}}'
        .'&subzone_id={{adid}}'
        .'&cost={{price}}'
        .'&category={{category}}',

    'popcash' => 'SUB_ID=[clickid]'
        .'&campaign_id=[campaignid]'
        .'&country=[cc]'
        .'&language=[language]'
        .'&device=[device]'
        .'&os=[operatingsystem]'
        .'&browser=[browser]'
        .'&connection_type=[connection]'
        .'&carrier=[carrier]'
        .'&zoneid=[siteid]'
        .'&cost=[bid]',
];
