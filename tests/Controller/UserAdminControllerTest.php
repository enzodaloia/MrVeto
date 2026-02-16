<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $userRepository;
    private string $path = '/user/admin/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->userRepository = $this->manager->getRepository(User::class);

        foreach ($this->userRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'user[email]' => 'Testing',
            'user[roles]' => 'Testing',
            'user[password]' => 'Testing',
            'user[isVerified]' => 'Testing',
            'user[nom]' => 'Testing',
            'user[prenom]' => 'Testing',
            'user[datenaissance]' => 'Testing',
            'user[adresse]' => 'Testing',
            'user[telephone]' => 'Testing',
            'user[ville]' => 'Testing',
            'user[codepostal]' => 'Testing',
            'user[siret]' => 'Testing',
            'user[adressecabinet]' => 'Testing',
        ]);

        self::assertResponseRedirects('/user/admin');

        self::assertSame(1, $this->userRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = new User();
        $fixture->setEmail('My Title');
        $fixture->setRoles('My Title');
        $fixture->setPassword('My Title');
        $fixture->setIsVerified('My Title');
        $fixture->setNom('My Title');
        $fixture->setPrenom('My Title');
        $fixture->setDatenaissance('My Title');
        $fixture->setAdresse('My Title');
        $fixture->setTelephone('My Title');
        $fixture->setVille('My Title');
        $fixture->setCodepostal('My Title');
        $fixture->setSiret('My Title');
        $fixture->setAdressecabinet('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('User');

        // Use assertions to check that the properties are properly displayed.
        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = new User();
        $fixture->setEmail('Value');
        $fixture->setRoles('Value');
        $fixture->setPassword('Value');
        $fixture->setIsVerified('Value');
        $fixture->setNom('Value');
        $fixture->setPrenom('Value');
        $fixture->setDatenaissance('Value');
        $fixture->setAdresse('Value');
        $fixture->setTelephone('Value');
        $fixture->setVille('Value');
        $fixture->setCodepostal('Value');
        $fixture->setSiret('Value');
        $fixture->setAdressecabinet('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'user[email]' => 'Something New',
            'user[roles]' => 'Something New',
            'user[password]' => 'Something New',
            'user[isVerified]' => 'Something New',
            'user[nom]' => 'Something New',
            'user[prenom]' => 'Something New',
            'user[datenaissance]' => 'Something New',
            'user[adresse]' => 'Something New',
            'user[telephone]' => 'Something New',
            'user[ville]' => 'Something New',
            'user[codepostal]' => 'Something New',
            'user[siret]' => 'Something New',
            'user[adressecabinet]' => 'Something New',
        ]);

        self::assertResponseRedirects('/user/admin');

        $fixture = $this->userRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getEmail());
        self::assertSame('Something New', $fixture[0]->getRoles());
        self::assertSame('Something New', $fixture[0]->getPassword());
        self::assertSame('Something New', $fixture[0]->getIsVerified());
        self::assertSame('Something New', $fixture[0]->getNom());
        self::assertSame('Something New', $fixture[0]->getPrenom());
        self::assertSame('Something New', $fixture[0]->getDatenaissance());
        self::assertSame('Something New', $fixture[0]->getAdresse());
        self::assertSame('Something New', $fixture[0]->getTelephone());
        self::assertSame('Something New', $fixture[0]->getVille());
        self::assertSame('Something New', $fixture[0]->getCodepostal());
        self::assertSame('Something New', $fixture[0]->getSiret());
        self::assertSame('Something New', $fixture[0]->getAdressecabinet());

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = new User();
        $fixture->setEmail('Value');
        $fixture->setRoles('Value');
        $fixture->setPassword('Value');
        $fixture->setIsVerified('Value');
        $fixture->setNom('Value');
        $fixture->setPrenom('Value');
        $fixture->setDatenaissance('Value');
        $fixture->setAdresse('Value');
        $fixture->setTelephone('Value');
        $fixture->setVille('Value');
        $fixture->setCodepostal('Value');
        $fixture->setSiret('Value');
        $fixture->setAdressecabinet('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/user/admin');
        self::assertSame(0, $this->userRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}
