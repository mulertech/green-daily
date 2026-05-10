<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\Sex;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:user:create', description: 'Create a new user account')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $emailQ = new Question('Email: ');
        $emailQ->setValidator(function (?string $value): string {
            $value = (string) $value;
            $errors = $this->validator->validate($value, [new NotBlank(), new Email()]);
            if (count($errors) > 0) {
                throw new \RuntimeException((string) $errors[0]->getMessage());
            }

            return $value;
        });
        $email = (string) $helper->ask($input, $output, $emailQ);

        if (null !== $this->users->findOneByEmail($email)) {
            $io->error(sprintf('User "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $passwordQ = new Question('Password: ');
        $passwordQ->setHidden(true);
        $passwordQ->setValidator(function (?string $value): string {
            $value = (string) $value;
            if (strlen($value) < 8) {
                throw new \RuntimeException('Password must be at least 8 characters.');
            }

            return $value;
        });
        $password = (string) $helper->ask($input, $output, $passwordQ);

        $sexQ = new ChoiceQuestion('Sex', ['male', 'female'], 0);
        $sex = Sex::from((string) $helper->ask($input, $output, $sexQ));

        $birthQ = new Question('Birth date (YYYY-MM-DD, optional): ', '');
        $birthRaw = (string) $helper->ask($input, $output, $birthQ);
        $birthDate = '' === $birthRaw ? null : new \DateTimeImmutable($birthRaw);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setSex($sex);
        $user->setBirthDate($birthDate);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('User "%s" created.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
