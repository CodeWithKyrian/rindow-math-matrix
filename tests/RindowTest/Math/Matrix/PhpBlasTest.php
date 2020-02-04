<?php
namespace RindowTest\Math\Matrix\PhpBlasTest;

use PHPUnit\Framework\TestCase;
use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\BLAS;
use Rindow\Math\Matrix\MatrixOperator;
use Rindow\Math\Matrix\PhpBlas;
use InvalidArgumentException;
use RuntimeException;

class Test extends TestCase
{
    public function getBlas($mo)
    {
        $blas = new PhpBlas();
        return $blas;
    }

    public function translate_gemm(
        NDArray $A,
        NDArray $B,
        float $alpha=null,
        float $beta=null,
        NDArray $C=null,
        bool $transA=null,
        bool $transB=null)
    {
        $shapeA = $A->shape();
        if($transA) {
            $shapeA = [$shapeA[1],$shapeA[0]];
        }
        $shapeB = $B->shape();
        if($transB) {
            $shapeB = [$shapeB[1],$shapeB[0]];
        }
        if($shapeA[1]!=$shapeB[0]) {
            throw new InvalidArgumentException('The number of columns in "A" and the number of rows in "B" must be the same');
        }
        $AA = $A->buffer();
        $BB = $B->buffer();
        $offA = $A->offset();
        $offB = $B->offset();
        $M = $shapeA[0];
        $N = $shapeB[1];
        $K = $shapeA[1];

        if($alpha===null) {
            $alpha = 1.0;
        }
        if($beta===null) {
            $beta = 0.0;
        }
        if($C!=null) {
            $shapeC = $C->shape();
            if($M!=$shapeC[0] || $N!=$shapeC[1]) {
                throw new InvalidArgumentException('"A" and "C" must have the same number of rows."B" and "C" must have the same number of columns');
            }
        } else {
            $C = $this->mo->zeros([$M,$N]);
        }
        $CC = $C->buffer();
        $offC = $C->offset();

        $lda = ($transA) ? $M : $K;
        $ldb = ($transB) ? $K : $N;
        $ldc = $N;
        $transA = ($transA) ? BLAS::Trans : BLAS::NoTrans;
        $transB = ($transB) ? BLAS::Trans : BLAS::NoTrans;

        return [
            $transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc,
        ];
    }

    public function translate_gemv(
        NDArray $A,
        NDArray $X,
        float $alpha=null,
        float $beta=null,
        NDArray $Y=null,
        bool $trans=null)
    {
        if($A->ndim()!=2 || $X->ndim()!=1) {
            throw new InvalidArgumentException('"A" must be 2D-NDArray and "X" must 1D-NDArray.');
        }
        $shapeA = $A->shape();
        $shapeX = $X->shape();
        $rows = (!$trans) ? $shapeA[0] : $shapeA[1];
        $cols = (!$trans) ? $shapeA[1] : $shapeA[0];
        if($cols!=$shapeX[0]) {
            throw new InvalidArgumentException('The number of columns in "A" and The number of item in "X" must be the same');
        }
        $AA = $A->buffer();
        $XX = $X->buffer();
        $offA = $A->offset();
        $offX = $X->offset();
        $m = $shapeA[0];
        $n = $shapeA[1];
        if($alpha===null) {
            $alpha = 1.0;
        }
        if($beta===null) {
            $beta = 0.0;
        }
        if($Y!=null) {
            if($Y->ndim()!=1) {
                throw new InvalidArgumentException('"Y" must 1D-NDArray.');
            }
            $shapeY = $Y->shape();
            if($rows!=$shapeY[0]) {
                throw new InvalidArgumentException('The number of rows in "A" and The number of item in "Y" must be the same');
            }
        } else {
            $Y = $this->mo->zeros([$rows]);
        }
        $YY = $Y->buffer();
        $offY = $Y->offset();
        $trans = (!$trans) ? BLAS::NoTrans : BLAS::Trans;

        return [
            $trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,1,
            $beta,
            $YY,$offY,1,
        ];
    }

    public function testGemmNormal()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2,3],[4,5,6],[7,8,9]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,3]);
        $transA = false;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,2,3],
            [4,5,6],
            [7,8,9]
        ],$C->toArray());
    }

    public function testGemmTransposeSquareA()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2,3],[4,5,6],[7,8,9]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,3]);
        $transA = true;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,4,7],
            [2,5,8],
            [3,6,9]
        ],$C->toArray());
    }

    public function testGemmTransposeSquareB()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2,3],[4,5,6],[7,8,9]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,3]);
        $transA = false;
        $transB = true;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,4,7],
            [2,5,8],
            [3,6,9]
        ],$C->toArray());
    }

    public function testGemmNoTransRectangleA23()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2,3],[4,5,6]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([2,3]);
        $transA = false;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,2,3],
            [4,5,6],
        ],$C->toArray());
    }

    public function testGemmTransposeRectangleA32()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2],[3,4],[5,6]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([2,3]);
        $transA = true;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,3,5],
            [2,4,6],
        ],$C->toArray());
    }

    public function testGemmNoTransRectangleB32()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2],[3,4],[5,6]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,2]);
        $transA = false;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,2],
            [3,4],
            [5,6],
        ],$C->toArray());
    }

    public function testGemmTransposeRectangleB23()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2,3],[4,5,6]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,2]);
        $transA = false;
        $transB = true;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);

        $this->assertEquals([
            [1,4],
            [2,5],
            [3,6],
        ],$C->toArray());
    }

    public function testGemmMatrixAOverFlowTransposeRectangleA32()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2],[3,4],[5,6]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([2,3]);
        $transA = true;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $AA = $mo->array([1,2,3,4,5])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferA');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemmMatrixBOverFlowTransposeRectangleA32()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2],[3,4],[5,6]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([2,3]);
        $transA = true;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $BB = $mo->array([1,0,0, 0,1,0, 0,0])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferB');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemmOutputOverFlowTransposeRectangleA32()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,2],[3,4],[5,6]]);
        $B = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([2,3]);
        $transA = true;
        $transB = false;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);


        $CC = $mo->zeros([5])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferC');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemmMatrixAOverFlowTransposeRectangleB23()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2,3],[4,5,6]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,2]);
        $transA = false;
        $transB = true;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $AA = $mo->array([1,0,0, 0,1,0, 0,0])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferA');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemmMatrixBOverFlowTransposeRectangleB23()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2,3],[4,5,6]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,2]);
        $transA = false;
        $transB = true;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $BB = $mo->array([1,2,3,4,5])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferB');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemmOutputOverFlowTransposeRectangleB23()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);
        $A = $mo->array([[1,0,0],[0,1,0],[0,0,1]]);
        $B = $mo->array([[1,2,3],[4,5,6]]);
        $alpha = 1.0;
        $beta  = 0.0;
        $C = $mo->zeros([3,2]);
        $transA = false;
        $transB = true;

        [ $transA,$transB,$M,$N,$K,$alpha,$AA,$offA,$lda,
          $BB,$offB,$ldb,$beta,$CC,$offC,$ldc] =
            $this->translate_gemm($A,$B,$alpha,$beta,$C,$transA,$transB);

        $CC = $mo->zeros([5])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferC');
        $blas->gemm(
            BLAS::RowMajor,$transA,$transB,
            $M,$N,$K,
            $alpha,
            $AA,$offA,$lda,
            $BB,$offB,$ldb,
            $beta,
            $CC,$offC,$ldc);
    }

    public function testGemvNormal()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);

        $A = $mo->array([[1,2,3],[4,5,6]]);
        $X = $mo->array([100,10,1]);
        $Y = $mo->zeros([2]);

        [ $trans,$m,$n,$alpha,$AA,$offA,$n,
          $XX,$offX,$incX,$beta,$YY,$offY,$incY] =
            $this->translate_gemv($A,$X,null,null,$Y);

        $blas->gemv(
            BLAS::RowMajor,$trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,$incX,
            $beta,
            $YY,$offY,$incY);

        $this->assertEquals(
            [123,456]
        ,$Y->toArray());
    }

    public function testGemvTranspose()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);

        $A = $mo->array([[1,2,3],[4,5,6]]);
        $X = $mo->array([10,1]);
        $Y = $mo->zeros([3]);

        [ $trans,$m,$n,$alpha,$AA,$offA,$n,
          $XX,$offX,$incX,$beta,$YY,$offY,$incY] =
            $this->translate_gemv($A,$X,null,null,$Y,true);

        $blas->gemv(
            BLAS::RowMajor,$trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,$incX,
            $beta,
            $YY,$offY,$incY);

        $this->assertEquals(
            [14,25,36]
        ,$Y->toArray());
    }

    public function testGemvMatrixOverFlowNormal()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);

        $A = $mo->array([[1,2,3],[4,5,6]]);
        $X = $mo->array([100,10,1]);
        $Y = $mo->zeros([2]);

        [ $trans,$m,$n,$alpha,$AA,$offA,$n,
          $XX,$offX,$incX,$beta,$YY,$offY,$incY] =
            $this->translate_gemv($A,$X,null,null,$Y);

        $AA = $mo->array([1,2,3,4,5])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferA');
        $blas->gemv(
            BLAS::RowMajor,$trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,$incX,
            $beta,
            $YY,$offY,$incY);
    }

    public function testGemvVectorXOverFlowNormal()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);

        $A = $mo->array([[1,2,3],[4,5,6]]);
        $X = $mo->array([100,10,1]);
        $Y = $mo->zeros([2]);

        [ $trans,$m,$n,$alpha,$AA,$offA,$n,
          $XX,$offX,$incX,$beta,$YY,$offY,$incY] =
            $this->translate_gemv($A,$X,null,null,$Y);

        $XX = $mo->array([10,1])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferX');
        $blas->gemv(
            BLAS::RowMajor,$trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,$incX,
            $beta,
            $YY,$offY,$incY);
    }

    public function testGemvVectorYOverFlowNormal()
    {
        $mo = new MatrixOperator();
        $blas = $this->getBlas($mo);

        $A = $mo->array([[1,2,3],[4,5,6]]);
        $X = $mo->array([100,10,1]);
        $Y = $mo->zeros([2]);

        [ $trans,$m,$n,$alpha,$AA,$offA,$n,
          $XX,$offX,$incX,$beta,$YY,$offY,$incY] =
            $this->translate_gemv($A,$X,null,null,$Y);

        $YY = $mo->array([0])->buffer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Vector specification too large for bufferY');
        $blas->gemv(
            BLAS::RowMajor,$trans,
            $m,$n,
            $alpha,
            $AA,$offA,$n,
            $XX,$offX,$incX,
            $beta,
            $YY,$offY,$incY);
    }
}