<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\CommentVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

//    /**
//     * @return Comment[] Returns an array of Comment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Comment
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    public function getVoteCounts(Comment $comment): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin(CommentVote::class, 'v', 'WITH', 'c.id = v.comment')
            ->addSelect('SUM(CASE WHEN v.value = 1 THEN 1 ELSE 0 END) AS likeCount')
            ->addSelect('SUM(CASE WHEN v.value = -1 THEN 1 ELSE 0 END) AS dislikeCount')
            ->where('c.id = :commentId')
            ->setParameter('commentId', $comment->getId())
            ->groupBy('c.id');

        return $qb->getQuery()->getSingleResult();
    }
}
