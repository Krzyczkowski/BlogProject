<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\PostLike;
use App\Entity\Post;
use App\Entity\Report;
use App\Entity\Tag;
use App\Form\CategoryFormType;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostController extends AbstractController
{
    #[Route('/post', name: 'post_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $posts = $entityManager->getRepository(Post::class)->findAll();

        return $this->render('index.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/post/update', name: 'post_update')]
    public function update(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $posts = $entityManager->getRepository(Post::class)->findBy([
            'author' => $user
        ]);

        return $this->render('myposts.html.twig', [
            'posts' => $posts,
        ]);
    }
    #[Route('/post/edit/{id}', name: 'post_edit')]
    public function edit(Post $post, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Check if the logged-in user is the author of the post
        if ($post->getAuthor() !== $user) {
            throw $this->createAccessDeniedException('You are not allowed to edit this post.');
        }

        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setUpdatedAt(new \DateTimeImmutable());

            // Handle image upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image.');
                    return $this->render('edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $post->setImage($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Post updated successfully.');

            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        return $this->render('edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/post/new', name: 'post_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setAuthor($this->getUser());
            $post->setCreatedAt(new \DateTimeImmutable());
            $post->setUpdatedAt(new \DateTimeImmutable());

            // Handle image upload
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image.');
                    return $this->render('new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $post->setImage($newFilename);
            }

            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('post_index');
        }

        return $this->render('new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/post/{id}', name: 'post_show')]
    public function show(Post $post, Request $request, EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setPost($post);
            $comment->setAuthor($this->getUser());
            $comment->setCreatedAt(new \DateTimeImmutable());
            $comment->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        $comments = $post->getComments();
        $likeCount = $post->getLikeCount();

        return $this->render('show.html.twig', [
            'post' => $post,
            'commentForm' => $form->createView(),
            'comments' => $comments,
            'likeCount' => $likeCount,
        ]);
    }
    #[Route('/post/delete/{id}', name: 'post_delete')]
    public function delete(Post $post, EntityManagerInterface $entityManager, Request $request): RedirectResponse
    {
        $user = $this->getUser();

        // Check if the user is the author of the post or an admin
        if (!$user || ($post->getAuthor() !== $user && !$this->isGranted('ROLE_ADMIN'))) {
            throw $this->createAccessDeniedException('You are not allowed to delete this post.');
        }

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'Post deleted successfully.');

        return $this->redirectToRoute('post_index');
    }
    #[Route('/admin', name: 'admin_dashboard')]
    public function admin_index(EntityManagerInterface $entityManager, Request $request): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $reports = $entityManager->getRepository(Report::class)->findAll();

        $newCategory = new Category();
        $categoryForm = $this->createForm(CategoryFormType::class, $newCategory);
        $categoryForm->handleRequest($request);

        if ($categoryForm->isSubmitted() && $categoryForm->isValid()) {
            $entityManager->persist($newCategory);
            $entityManager->flush();

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin.html.twig', [
            'categories' => $categories,
            'categoryForm' => $categoryForm->createView(),
            'reports' => $reports,
        ]);
    }
    #[Route('/post/like/{id}', name: 'post_like')]
    public function like(Post $post, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $like = $entityManager->getRepository(PostLike::class)->findOneBy([
            'user' => $user,
            'post' => $post,
        ]);

        if ($like) {

            $entityManager->remove($like);
            $this->addFlash('success', 'Like removed.');
        } else {

            $like = new PostLike();
            $like->setUser($user);
            $like->setPost($post);

            $entityManager->persist($like);
            $this->addFlash('success', 'Post liked.');
        }

        $entityManager->flush();

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }

    #[Route('/post/report/{id}', name: 'post_report')]
    public function report(Post $post, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $report = $entityManager->getRepository(Report::class)->findOneByUserAndPost($user, $post);

        if ($report) {
            $this->addFlash('error', 'You have already reported this post.');
        } else {
            $report = new Report();
            $report->setUser($user);
            $report->setPost($post);

            $entityManager->persist($report);
            $entityManager->flush();

            $this->addFlash('success', 'Post reported.');
        }

        return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
    }
    #[Route('/admin/category/{id}/delete', name: 'admin_category_delete')]
    public function deleteCategory(Category $category, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($category);
        $entityManager->flush();

        return $this->redirectToRoute('admin_dashboard');
    }
}