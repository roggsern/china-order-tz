import { mapProductReview } from './productReviewsApi';

describe('mapProductReview', () => {
  it('maps approved review payloads from the product reviews API', () => {
    expect(
      mapProductReview({
        id: 'r1',
        rating: 5,
        title: 'Great',
        comment: 'Fits well',
        author: 'Amina',
        verified: true,
        created_at: '2026-01-02T00:00:00Z',
      }),
    ).toMatchObject({
      id: 'r1',
      rating: 5,
      title: 'Great',
      comment: 'Fits well',
      author: 'Amina',
      verified: true,
    });
  });
});
