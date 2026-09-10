<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Development account, loaded in dev and test only, never in production: the fixtures bundle is
 * not registered there. Its password is known and written in clear here, which is acceptable only
 * because this account never crosses the boundary of the workstation.
 *
 * Loading is idempotent: `doctrine:fixtures:load --append` can be replayed without creating a
 * duplicate nor overwriting the foods imported from the CIQUAL table.
 */
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if (null !== $this->users->findOneByEmail('sebastien.muler@mulertech.net')) {
            return;
        }

        $user = new User();
        $user->setEmail('sebastien.muler@mulertech.net');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));

        $manager->persist($user);
        $manager->flush();
    }
}
