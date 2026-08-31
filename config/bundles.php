<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    SymfonyCasts\Bundle\ResetPassword\SymfonyCastsResetPasswordBundle::class => ['all' => true],
    Symfonycasts\TailwindBundle\SymfonycastsTailwindBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    MulerTech\CspBundle\MulerTechCspBundle::class => ['all' => true],
    // Development answers 404 all day long and the test client sends no User-Agent, both of
    // which the bundle reads as hostile. Production is the only place it has anything to judge.
    MulerTech\ScannerBan\MulerTechScannerBanBundle::class => ['prod' => true],
];
