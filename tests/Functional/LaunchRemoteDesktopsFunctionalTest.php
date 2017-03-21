<?php

namespace Tests\Functional;

use AppBundle\Entity\CloudInstance\CloudInstance;
use AppBundle\Entity\RemoteDesktop\RemoteDesktop;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Helpers\Helpers;

class LaunchRemoteDesktopsFunctionalTest extends WebTestCase
{
    use Helpers;

    protected function verifyDektopStatusBooting(Client $client, Crawler $crawler)
    {
        $link = $crawler->selectLink('Refresh status')->first()->link();
        $crawler = $client->click($link);

        $this->assertContains('My first remote desktop', $crawler->filter('h2')->first()->text());

        $this->assertContains('Current status:', $crawler->filter('h3')->first()->text());
        $this->assertContains('Booting...', $crawler->filter('span.label')->first()->text());

        $this->assertContains('Refresh status', $crawler->filter('.panel-footer a.btn')->first()->text());
        $this->assertEquals(
            1,
            $crawler->filter('.panel-footer a.btn')->count()
        );
    }

    public function testLaunchRemoteDesktop()
    {
        $client = (new CreateRemoteDesktopsFunctionalTest())->testCreateRemoteDesktop();

        $crawler = $client->request('GET', '/en/remoteDesktops/');

        $link = $crawler->selectLink('Launch this remote desktop')->first()->link();

        $crawler = $client->click($link);

        $container = $client->getContainer();
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $remoteDesktopRepo = $em->getRepository('AppBundle\Entity\RemoteDesktop\RemoteDesktop');
        /** @var RemoteDesktop $remoteDesktop */
        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first remote desktop']);
        $this->assertEquals(
            '/en/remoteDesktops/' . $remoteDesktop->getId() . '/cloudInstances/new',
            $client->getRequest()->getRequestUri()
        );

        $this->assertContains('Launch your remote desktop', $crawler->filter('h1')->first()->text());

        $buttonNode = $crawler->selectButton('Launch now');
        $form = $buttonNode->form();

        $client->submit($form, [
            'form[region]' => 'eu-central-1',
        ]);

        $crawler = $client->followRedirect();

        // We want to be back in the overview
        $this->assertEquals(
            '/en/remoteDesktops/',
            $client->getRequest()->getRequestUri()
        );


        // At this point, the instance is in "Scheduled for launch" state

        $this->verifyDektopStatusBooting($client, $crawler);


        // Switching instance to "Launching" status, which must not change the desktop status

        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first remote desktop']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_LAUNCHING);
        $em->persist($cloudInstance);
        $em->flush();

        $this->verifyDektopStatusBooting($client, $crawler);


        // Switching instance to "Running" status, which must put the desktop into "Ready to use" status

        $remoteDesktop = $remoteDesktopRepo->findOneBy(['title' => 'My first remote desktop']);
        /** @var CloudInstance $cloudInstance */
        $cloudInstance = $remoteDesktop->getCloudInstances()->get(0);
        $cloudInstance->setPublicAddress('121.122.123.124');
        $cloudInstance->setAdminPassword('foo');
        $cloudInstance->setRunstatus(CloudInstance::RUNSTATUS_RUNNING);
        $em->persist($cloudInstance);
        $em->flush();

        $link = $crawler->selectLink('Refresh status')->first()->link();
        $crawler = $client->click($link);

        $this->assertContains('My first remote desktop', $crawler->filter('h2')->first()->text());

        $this->assertContains('Current status:', $crawler->filter('h3')->first()->text());
        $this->assertContains('Ready to use', $crawler->filter('span.label')->first()->text());
        $this->assertContains('Stop this remote desktop', $crawler->filter('a.remotedesktop-action-button')->first()->text());

        $this->assertContains('IP address:', $crawler->filter('li.list-group-item')->eq(0)->text());
        $this->assertContains('121.122.123.124', $crawler->filter('li.list-group-item')->eq(0)->text());

        $this->assertContains('Username:', $crawler->filter('li.list-group-item')->eq(1)->text());
        $this->assertContains('Administrator', $crawler->filter('li.list-group-item')->eq(1)->text());

        $this->assertContains('Password:', $crawler->filter('li.list-group-item')->eq(2)->text());
        $this->assertContains('foo', $crawler->filter('li.list-group-item')->eq(2)->text());


        // We want to build on this in other tests
        return $client;
    }

}