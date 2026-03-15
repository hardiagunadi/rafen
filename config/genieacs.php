<?php

return [
    'nbi_url'  => env('GENIEACS_NBI_URL', 'http://localhost:7557'),
    'username' => env('GENIEACS_NBI_USERNAME', ''),
    'password' => env('GENIEACS_NBI_PASSWORD', ''),
    'timeout'  => (int) env('GENIEACS_NBI_TIMEOUT', 10),

    /*
    | Minutes since last TR-069 inform before a device is considered offline.
    | Default 70 = slightly over 1-hour inform interval (GenieACS default).
    | Tune with GENIEACS_ONLINE_THRESHOLD_MINUTES in .env.
    */
    'online_threshold_minutes' => (int) env('GENIEACS_ONLINE_THRESHOLD_MINUTES', 70),

    /*
    |--------------------------------------------------------------------------
    | TR-069 Parameter Paths
    |--------------------------------------------------------------------------
    | Two profiles supported:
    |   - "igd" : InternetGatewayDevice.* (TR-098, older CPE — most XPON/EPON ONUs)
    |   - "device" : Device.* (TR-181 Device:2, newer CPE)
    |
    | GenieAcsClient auto-detects which root the device uses.
    */
    'params' => [
        // TR-098 (InternetGatewayDevice) paths — used by H3-2S XPON and similar
        'igd' => [
            'pppoe_username'   => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username',
            'pppoe_password'   => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Password',
            'wifi_ssid'        => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'wifi_password'    => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
            'firmware_version' => 'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
            'model'            => 'InternetGatewayDevice.DeviceInfo.ModelName',
            'manufacturer'     => 'InternetGatewayDevice.DeviceInfo.Manufacturer',
            'serial_number'    => 'InternetGatewayDevice.DeviceInfo.SerialNumber',
            'uptime'           => 'InternetGatewayDevice.DeviceInfo.UpTime',
            // Multi-SSID — {idx} replaced at runtime with instance number (1,3,5,7,...)
            'wifi_ssid_n'      => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.SSID',
            'wifi_password_n'  => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.KeyPassphrase',
            'wifi_enable_n'    => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.{idx}.Enable',
            // WAN — {wd}.{cd}.{conn} replaced at runtime
            'wan_username'     => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Username',
            'wan_password'     => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Password',
            'wan_enable'       => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.Enable',
            'wan_nat'          => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.NATEnabled',
            'wan_dns'          => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.DNSServers',
            'wan_conn_type'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.ConnectionType',
            'wan_vlan'         => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.X_CT-COM_WANEponLinkConfig.VLANIDMark',
            'wan_vlan_prio'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.X_CT-COM_WANEponLinkConfig.802-1pMark',
            'wan_lan_iface'    => 'InternetGatewayDevice.WANDevice.{wd}.WANConnectionDevice.{cd}.WANPPPConnection.{conn}.X_CT-COM_LanInterface',
        ],
        // TR-181 (Device) paths — newer CPE
        'device' => [
            'pppoe_username'   => 'Device.PPP.Interface.1.Username',
            'pppoe_password'   => 'Device.PPP.Interface.1.Password',
            'wifi_ssid'        => 'Device.WiFi.SSID.1.SSID',
            'wifi_password'    => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
            'firmware_version' => 'Device.DeviceInfo.SoftwareVersion',
            'model'            => 'Device.DeviceInfo.ModelName',
            'manufacturer'     => 'Device.DeviceInfo.Manufacturer',
            'serial_number'    => 'Device.DeviceInfo.SerialNumber',
            'uptime'           => 'Device.DeviceInfo.UpTime',
        ],
    ],
];
