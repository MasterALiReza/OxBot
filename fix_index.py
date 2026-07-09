import sys

with open('c:/Users/iWexort/Documents/Github/mirzabot-main/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """        $randomString = bin2hex(random_bytes(4));
        if (in_array($randomString, $id_invoice)) {
            $randomString = $random_number . $randomString;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);"""

replacement = """        $randomString = bin2hex(random_bytes(4));
        if (in_array($randomString, $id_invoice)) {
            $randomString = $random_number . $randomString;
        }
        
        $Status = "active";
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
        $stmt->execute();
        $stmt->close();

        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac);
        if ($dataoutput['username'] == null) {
            $stmt = $connect->prepare("DELETE FROM invoice WHERE id_invoice = ?");
            $stmt->bind_param("s", $randomString);
            $stmt->execute();
            $stmt->close();
            
            $dataoutput['msg'] = json_encode($dataoutput['msg']);"""

content = content.replace(target, replacement)

target2 = """            step('home', $from_id);
            return;
        }
        $stmt = $connect->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
        $Status = "active";
        $stmt->bind_param("sssssssssss", $from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions);
        $stmt->execute();
        $stmt->close();
        $service_links = formatServiceDeliveryLinks($marzban_list_get, $dataoutput);"""

replacement2 = """            step('home', $from_id);
            return;
        }
        
        $service_links = formatServiceDeliveryLinks($marzban_list_get, $dataoutput);"""

content = content.replace(target2, replacement2)

with open('c:/Users/iWexort/Documents/Github/mirzabot-main/index.php', 'w', encoding='utf-8') as f:
    f.write(content)
print('Done!')
