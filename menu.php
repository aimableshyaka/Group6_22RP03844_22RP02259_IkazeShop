<?php
include 'db.php';
include 'sms.php';

class Menu {
    protected $text;
    protected $sessionId;
    protected $pdo;
    protected $phoneNumber;   

    function __construct($text, $sessionId, $phoneNumber = null) {

        global $pdo;
        $this->text = $text;
        $this->sessionId = $sessionId;
        $this->pdo = $pdo;
        $this->phoneNumber = $phoneNumber;
    }
       public function isRegistered($phoneNumber) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE phoneNumber = ?");
        $stmt->execute([$phoneNumber]);
        return $stmt->rowCount() > 0;
    }
        public function mainMenuUnregistered() {
        $response = "CON Welcome to Ikaze Shop \n";
        $response .= "1. Register \n";
        $response .= "2. Contact\n" ;
        $response .= "2. About";
        echo $response;
    }

        public function menuRegister($textArray) {
        $level = count($textArray);

        if($level == 1) {
            echo "CON Enter your fullname \n";
        }
        else if($level == 2) {
            echo "CON Enter your Email \n";
        }
        else if($level == 3) {
            echo "CON -enter your PIN \n";
        }
        else if($level == 4) {
            echo "CON Re-enter your PIN \n";
        }
        else if($level == 5) {
            $name = $textArray[1];
            $email=$textArray[2];
            $pin = $textArray[3];
            $confirm_pin = $textArray[4];
            
            if($pin != $confirm_pin) {
                echo "END PINs do not match, Retry";
            } else {
                try {
                    $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
                    $stmt = $this->pdo->prepare("INSERT INTO users (phoneNumber, name,email, pin) VALUES (?, ?, ?,?)");
                    $stmt->execute([$this->phoneNumber, $name,$email, $hashed_pin]);
                    
                    // Send welcome SMS
                    $sms = new SMS();
                $sms->sendWelcomeSMS($this->phoneNumber, $name);
                
                echo "END Dear $name, you have successfully registered as a user \n SMS will come shortly";
                } catch(PDOException $e) {
                    echo "END Registration failed. Please try again.".$e->getmessage();
                }
            }
        }

        
    }

        public function mainMenuUser() {
        $response = "CON Welcome to Ikaze-Shop USSD\n";
        $response .= "1. Produc List\n";
        $response .= "2. My Cart\n";
        $response .= "3. My Account\n";
        $response .= "4. Customer Support";
        echo $response;
    }

public function productList($textArray = [], $level = 1) {
    if ($level > 1 && end($textArray) == "0") {
        $this->mainMenuUser();
        return;
    }

    try {
        // If user selects a product (e.g., "1*3")
        if ($level == 2) {
            $selectedProductId = intval($textArray[1]);
            
            // Insert into cart
            $stmt = $this->pdo->prepare("INSERT INTO cart (user_id, product_id, added_at) VALUES (?, ?, NOW())");
            $stmt->execute([$this->getUserIdByPhone($this->phoneNumber), $selectedProductId]);

            echo "CON Product added to cart successfully!\n";
            echo "98. Back to Main Menu";
            return;
        }

        // Else, show product list
        $stmt = $this->pdo->prepare("SELECT id, name, description, price FROM products");
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            echo "END No products available at the moment";
            return;
        }

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = "CON Available Products:\n\n";
        foreach($products as $product) {
            $response .= $product['id'] . ". " . $product['name'] . "\n";
            $response .= "Price: " . number_format($product['price'], 2) . "\n";
            $shortDesc = strlen($product['description']) > 30 ? 
                         substr($product['description'], 0, 30) . "..." : 
                         $product['description'];
            $response .= $shortDesc . "\n";
            $response .= "------------------------\n";
        }

        $response .= "Reply with the product number to add to cart\n";
        $response .= "0. Back to Main Menu";
        echo $response;

    } catch(PDOException $e) {
        echo "END Error: " . $e->getMessage(); // Show actual error while debugging
    }
}
private function getUserIdByPhone($phone) {
    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE phoneNumber = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ? $user['id'] : null;
}


public function Mycart() {
    try {
        $smsContent = "Your Ikaze Shop Cart:\n";
        // Use phoneNumber to get the user_id
        $stmtUser = $this->pdo->prepare("SELECT id, name FROM users WHERE phoneNumber = ?");
        $stmtUser->execute([$this->phoneNumber]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo "END User not found.";
            return;
        }

        $userId = $user['id'];

        // Fetch cart with product and user info
        $stmt = $this->pdo->prepare("
            SELECT 
                products.name AS product_name, 
                products.description, 
                products.price, 
                cart.quantity, 
                users.name AS user_name
            FROM cart
            INNER JOIN products ON cart.product_id = products.id
            INNER JOIN users ON cart.user_id = users.id
            WHERE cart.user_id = ?
        ");
        $stmt->execute([$userId]);

        if ($stmt->rowCount() == 0) {
            echo "END Your cart is empty.";
            return;
        }

        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build response
        $response = "CON Dear " . $user['name'] . ", here is your cart:\n\n";
        foreach ($cartItems as $item) {
            $response .= "Product: " . $item['product_name'] . "\n";
            $response .= "Price: " . number_format($item['price'], 2) . "\n";
            $response .= "Qty: " . $item['quantity'] . "\n";
            $shortDesc = strlen($item['description']) > 30 ? substr($item['description'], 0, 30) . "..." : $item['description'];
            $response .= "Desc: " . $shortDesc . "\n";
            $response .= "------------------------\n";
        }
        $response.="Check your sms to review you cart\n";
        $response .= "0. Back to Main Menu";
        echo $response;
  
        $sms = new SMS();
        $sms->sendCartSummarySMS($this->phoneNumber, $smsContent);
    } catch (PDOException $e) {
        echo "END Error loading cart: " . $e->getMessage();
    }
}

public function CustomerSupport(){
    $response = "END Customer Support Team\n";
    $response .= "Phone: +25078507360\n";
    $response .= "Email: ikazeshop2025@gmail.com\n";
    $response .= "0.Going Back";
    echo $response;
}
public function Account() {
    try {
        // Fetch user details
        $stmt = $this->pdo->prepare("SELECT name, phoneNumber, created_at FROM users WHERE phoneNumber = ?");
        $stmt->execute([$this->phoneNumber]);

        if ($stmt->rowCount() == 0) {
            echo "END Account not found.";
            return;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Display account info
        $response = "END My Account\n";
        $response .= "Name: " . $user['name'] . "\n";
        $response .= "Phone: " . $user['phoneNumber'] . "\n";
        $response .= "Registered On: " . date("Y-m-d", strtotime($user['created_at']));
        $response.="\n0. Back";

        echo $response;
    } catch (PDOException $e) {
        echo "END Error loading account info.";
        error_log("Account error: " . $e->getMessage());
    }
}

}

?>
