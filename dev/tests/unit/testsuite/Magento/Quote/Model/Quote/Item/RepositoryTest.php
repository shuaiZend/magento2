<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Quote\Model\Quote\Item;

class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quoteRepositoryMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $productRepositoryMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $dataMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quoteMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $productMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $quoteItemMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $itemDataFactoryMock;

    protected function setUp()
    {
        $this->quoteRepositoryMock =
            $this->getMock('\Magento\Quote\Model\QuoteRepository', [], [], '', false);
        $this->productRepositoryMock =
            $this->getMock('Magento\Catalog\Api\ProductRepositoryInterface', [], [], '', false);
        $this->itemDataFactoryMock =
            $this->getMock('Magento\Quote\Api\Data\CartItemInterfaceFactory', ['create'], [], '', false);
        $this->dataMock = $this->getMock('Magento\Quote\Api\Data\CartItemInterface');
        $this->quoteMock = $this->getMock('\Magento\Quote\Model\Quote', [], [], '', false);
        $this->productMock = $this->getMock('\Magento\Catalog\Model\Product', [], [], '', false);
        $this->quoteItemMock =
            $this->getMock('Magento\Quote\Model\Quote\Item', ['getId', 'getSku', 'setData', '__wakeUp'], [], '', false);

        $this->repository = new Repository(
            $this->quoteRepositoryMock,
            $this->productRepositoryMock,
            $this->itemDataFactoryMock
        );
    }

    /**
     * @param null|string|bool|int|float $value
     * @expectedException \Magento\Framework\Exception\InputException
     * @expectedExceptionMessage Invalid value of
     * @dataProvider addItemWithInvalidQtyDataProvider
     */
    public function testSaveItemWithInvalidQty($value)
    {
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue($value));
        $this->repository->save($this->dataMock);
    }

    public function addItemWithInvalidQtyDataProvider()
    {
        return [
            ['string'],
            [0],
            [''],
            [null],
            [-12],
            [false],
            [-13.1],
        ];
    }

    /**
     * @expectedException \Magento\Framework\Exception\CouldNotSaveException
     * @expectedExceptionMessage Could not save quote
     */
    public function testSaveCouldNotSaveException()
    {
        $cartId = 13;
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue(12));
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->quoteRepositoryMock->expects($this->once())->method('getActive')
            ->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->dataMock->expects($this->once())->method('getSku')->will($this->returnValue('product_sku'));
        $this->dataMock->expects($this->once())->method('getItemId')->will($this->returnValue(null));
        $this->quoteMock->expects($this->never())->method('getItemById');
        $this->productRepositoryMock->expects($this->once())
            ->method('get')->with('product_sku')->will($this->returnValue($this->productMock));
        $this->quoteMock->expects($this->once())->method('addProduct')->with($this->productMock, 12);
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $exceptionMessage = 'Could not save quote';
        $exception = new \Magento\Framework\Exception\CouldNotSaveException($exceptionMessage);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock)
            ->willThrowException($exception);

        $this->repository->save($this->dataMock);
    }

    public function testSave()
    {
        $cartId = 13;
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue(12));
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->productRepositoryMock->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this->productMock));
        $this->dataMock->expects($this->once())->method('getSku');
        $this->quoteMock->expects($this->once())->method('addProduct')->with($this->productMock, 12);
        $this->quoteMock->expects($this->never())->method('getItemById');
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $this->quoteRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);
        $this->quoteMock
            ->expects($this->once())
            ->method('getItemByProduct')
            ->with($this->productMock)
            ->will($this->returnValue($this->quoteItemMock));
        $this->dataMock->expects($this->once())->method('getItemId')->will($this->returnValue(null));
        $this->assertEquals($this->quoteItemMock, $this->repository->save($this->dataMock));
    }

    /**
     * @expectedException \Magento\Framework\Exception\NoSuchEntityException
     * @expectedExceptionMessage Cart 11 doesn't contain item  5
     */
    public function testUpdateItemWithInvalidQuoteItem()
    {
        $cartId = 11;
        $itemId = 5;
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue(12));
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->dataMock->expects($this->once())->method('getItemId')->will($this->returnValue($itemId));
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue(false));
        $this->quoteItemMock->expects($this->never())->method('setData');
        $this->quoteItemMock->expects($this->never())->method('addProduct');

        $this->repository->save($this->dataMock);
    }

    /**
     * @expectedException \Magento\Framework\Exception\CouldNotSaveException
     * @expectedExceptionMessage Could not save quote
     */
    public function testUpdateItemWithCouldNotSaveException()
    {
        $cartId = 11;
        $itemId = 5;
        $productSku = 'product_sku';
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue(12));
        $this->dataMock->expects($this->once())->method('getItemId')->will($this->returnValue($itemId));
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue($this->quoteItemMock));
        $this->quoteItemMock->expects($this->once())->method('setData')->with('qty', 12);
        $this->quoteItemMock->expects($this->once())->method('getSku')->willReturn($productSku);
        $this->productRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($productSku)
            ->willReturn($this->productMock);
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $this->quoteItemMock->expects($this->never())->method('addProduct');
        $exceptionMessage = 'Could not save quote';
        $exception = new \Magento\Framework\Exception\CouldNotSaveException($exceptionMessage);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock)
            ->willThrowException($exception);

        $this->repository->save($this->dataMock);
    }

    public function testUpdateItem()
    {
        $cartId = 11;
        $itemId = 5;
        $productSku = 'product_sku';
        $this->dataMock->expects($this->once())->method('getQty')->will($this->returnValue(12));
        $this->dataMock->expects($this->once())->method('getItemId')->will($this->returnValue($itemId));
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue($this->quoteItemMock));
        $this->quoteItemMock->expects($this->once())->method('setData')->with('qty', 12);
        $this->quoteItemMock->expects($this->once())->method('getSku')->willReturn($productSku);
        $this->productRepositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($productSku)
            ->willReturn($this->productMock);
        $this->quoteItemMock->expects($this->never())->method('addProduct');
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $this->quoteRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);
        $this->quoteMock
            ->expects($this->once())
            ->method('getItemByProduct')
            ->with($this->productMock)
            ->willReturn($this->quoteItemMock);
        $this->assertEquals($this->quoteItemMock, $this->repository->save($this->dataMock));
    }

    /**
     * @expectedException \Magento\Framework\Exception\NoSuchEntityException
     * @expectedExceptionMessage Cart 11 doesn't contain item  5
     */
    public function testDeleteWithInvalidQuoteItem()
    {
        $cartId = 11;
        $itemId = 5;
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->dataMock->expects($this->once())->method('getItemId')->willReturn($itemId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue(false));
        $this->quoteMock->expects($this->never())->method('removeItem');

        $this->repository->delete($this->dataMock);
    }

    /**
     * @expectedException \Magento\Framework\Exception\CouldNotSaveException
     * @expectedExceptionMessage Could not remove item from quote
     */
    public function testDeleteWithCouldNotSaveException()
    {
        $cartId = 11;
        $itemId = 5;
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->dataMock->expects($this->once())->method('getItemId')->willReturn($itemId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue($this->quoteItemMock));
        $this->quoteMock->expects($this->once())
            ->method('removeItem')->with($itemId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $exceptionMessage = 'Could not remove item from quote';
        $exception = new \Magento\Framework\Exception\CouldNotSaveException($exceptionMessage);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock)
            ->willThrowException($exception);

        $this->repository->delete($this->dataMock);
    }

    public function testDelete()
    {
        $cartId = 11;
        $itemId = 5;
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->dataMock->expects($this->once())->method('getItemId')->willReturn($itemId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue($this->quoteItemMock));
        $this->quoteMock->expects($this->once())->method('removeItem');
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $this->quoteRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);

        $this->repository->delete($this->dataMock);
    }

    public function testGetList()
    {
        $quoteMock = $this->getMock('Magento\Quote\Model\Quote', [], [], '', false);
        $this->quoteRepositoryMock->expects($this->once())->method('getActive')
            ->with(33)
            ->will($this->returnValue($quoteMock));
        $itemMock = $this->getMock('\Magento\Quote\Model\Quote\Item', [], [], '', false);
        $quoteMock->expects($this->any())->method('getAllItems')->will($this->returnValue([$itemMock]));

        $this->assertEquals([$itemMock], $this->repository->getList(33));
    }

    public function testDeleteById()
    {
        $cartId = 11;
        $itemId = 5;
        $this->itemDataFactoryMock->expects($this->once())->method('create')->willReturn($this->dataMock);
        $this->dataMock->expects($this->once())->method('setQuoteId')
            ->with($cartId)->willReturn($this->dataMock);
        $this->dataMock->expects($this->once())->method('setItemId')
            ->with($itemId)->willReturn($this->dataMock);
        $this->dataMock->expects($this->once())->method('getQuoteId')->willReturn($cartId);
        $this->dataMock->expects($this->once())->method('getItemId')->willReturn($itemId);
        $this->quoteRepositoryMock->expects($this->once())
            ->method('getActive')->with($cartId)->will($this->returnValue($this->quoteMock));
        $this->quoteMock->expects($this->once())
            ->method('getItemById')->with($itemId)->will($this->returnValue($this->quoteItemMock));
        $this->quoteMock->expects($this->once())->method('removeItem');
        $this->quoteMock->expects($this->once())->method('collectTotals')->will($this->returnValue($this->quoteMock));
        $this->quoteRepositoryMock->expects($this->once())->method('save')->with($this->quoteMock);

        $this->assertTrue($this->repository->deleteById($cartId, $itemId));
    }
}
