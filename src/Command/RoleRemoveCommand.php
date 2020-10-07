<?php

namespace App\Command;

use Kreait\Firebase\Auth;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use App\Entity\User;

class RoleRemoveCommand extends Command
{
    protected static $defaultName = 'app:role-remove';

    public function __construct(ContainerInterface $container, Auth $auth)
    {
        $this->container = $container;
        $this->auth = $auth;
        parent::__construct();
    }
    protected function configure()
    {
        $this->setDescription('Remove user role');
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        
        $io = new SymfonyStyle($input, $output);
        $email = $io->ask('Email');
        if(null == $email) return null;

        $firebase_user = $this->auth->getUserByEmail($email);
        if(null == $firebase_user) return null;

        $em = $this->container->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneByFirebaseUid( $firebase_user->uid );
        if(null == $user) return null;
        
        $role = $io->ask('Role Name');
        if(null == $role) return null;

        $roles = $user->getRoles();
        $_roles = [];
        foreach($roles as $_role){
            if($_role != $role) array_push($_roles, $_role);
        }
        $user->setRoles($_roles);

        $em->persist($user);
        $em->flush();

        $io->success('The following user has been removed role');
        
        return 0;
    }
}
