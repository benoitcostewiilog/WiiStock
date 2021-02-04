<?php

namespace App\Tests\Controller;


namespace App\Tests\Controller;

use App\Tests\WebTestCase;

class DashboardControllerTest extends WebTestCase {

    public function testDashboard() {
        $client = $this->createAnonymousClient();
        $client->request("GET", "/accueil");
        $this->assertResponseRedirects("http://localhost/login", 302);

        $client = $this->createAuthenticatedClient();
        $client->request("GET", "/accueil");
        $this->assertResponseIsSuccessful();
    }

}
